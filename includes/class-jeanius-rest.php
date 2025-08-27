<?php
namespace Jeanius;

class Rest {

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes() {
		register_rest_route( 'jeanius/v1', '/stage', [
			'methods'             => 'POST',
			'permission_callback' => function () { 
				return is_user_logged_in(); 
			},
			'callback'            => [ __CLASS__, 'save_stage' ],
		] );

		register_rest_route( 'jeanius/v1', '/review', [
			'methods'             => 'POST',
			'permission_callback' => function() { 
				return is_user_logged_in(); 
			},
			'callback'            => [ __CLASS__, 'save_order' ],
		] );

		// Save description for one word
		register_rest_route( 'jeanius/v1', '/describe', [
			'methods'             => 'POST',
			'permission_callback' => function() { 
				return is_user_logged_in(); 
			},
			'callback'            => [ __CLASS__, 'save_description' ],
		] );

               // Generate Jeanius report (OpenAI)
               // POST /wp-json/jeanius/v1/generate
               register_rest_route( 'jeanius/v1', '/generate', [
                       'methods'             => 'POST',
                       'permission_callback' => fn() => is_user_logged_in(),
                       'callback'            => function ( \WP_REST_Request $r ) {
                               return self::generate_report();
                       },
               ] );
        }

	public static function save_stage( \WP_REST_Request $r ) {
		$post_id = \Jeanius\current_assessment_id();
		
		if ( ! $post_id ) {
			return new \WP_Error( 'login_required', 'Login first', [ 'status' => 401 ] );
		}

		$stage_key = sanitize_text_field( $r->get_param( 'stage' ) );
		$entries   = $r->get_param( 'entries' );

		if ( ! $stage_key || empty( $entries ) ) {
			return new \WP_Error( 'missing', 'Missing data', [ 'status' => 400 ] );
		}

		$data = json_decode( get_field( 'stage_data', $post_id ) ?: '{}', true );
		$data[ $stage_key ] = array_values(
			array_filter( array_map( 'sanitize_text_field', $entries ) )
		);

		\update_field( 'stage_data', wp_json_encode( $data ), $post_id );

		return [ 'success' => true ];
	}

	/**
	 * Save reordered words during 5-minute review
	 * POST /jeanius/v1/review
	 * Body: { "ordered": { "early_childhood":[...], "elementary":[...] ... } }
	 */
	public static function save_order( \WP_REST_Request $r ) {
		$post_id = \Jeanius\current_assessment_id();
		
		if ( ! $post_id ) {
			return new \WP_Error( 'login', 'Login required', [ 'status' => 401 ] );
		}

		$ordered = $r->get_param( 'ordered' );
		
		if ( ! is_array( $ordered ) ) {
			return new \WP_Error( 'bad', 'Missing ordered data', [ 'status' => 400 ] );
		}

		\update_field( 'stage_data', wp_json_encode( $ordered ), $post_id );
		
		return [ 'success' => true ];
	}

	/**
	 * Helper to read/write progress count
	 */
	private static function stage_counter( int $post_id, string $stage, ?int $set = null ) {
		$key = "_{$stage}_done";
		
		if ( $set !== null ) {
			update_post_meta( $post_id, $key, $set );
		}
		
		return (int) get_post_meta( $post_id, $key, true );
	}

	public static function save_description( \WP_REST_Request $r ) {
		$post_id = \Jeanius\current_assessment_id();
		
		if ( ! $post_id ) {
			return new \WP_Error( 'login', 'Login', [ 'status' => 401 ] );
		}

		$stage   = sanitize_text_field( $r['stage'] );
		$index   = (int) $r['index'];
		$desc    = sanitize_textarea_field( $r['description'] );
		$pol     = $r['polarity'] === 'negative' ? 'negative' : 'positive';
		$rating  = min( 5, max( 1, (int) $r['rating'] ) );

		// Append to full_stage_data
		$full = json_decode( get_field( 'full_stage_data', $post_id ) ?: '{}', true );
		$full[ $stage ][] = [
			'title'       => $r['title'],
			'description' => $desc,
			'polarity'    => $pol,
			'rating'      => $rating,
		];
		
		update_field( 'full_stage_data', wp_json_encode( $full ), $post_id );

		// Bump progress counter
		$done = self::stage_counter( $post_id, $stage );
		self::stage_counter( $post_id, $stage, $done + 1 );

		return [ 'success' => true ];
	}

	public static function get_timeline_data( int $post_id ): array {
		$raw   = json_decode( get_field( 'full_stage_data', $post_id ) ?: '{}', true );
		$out   = [];
		$order = [ 'early_childhood', 'elementary', 'middle_school', 'high_school' ];
		
		foreach ( $order as $stage_idx => $stage_key ) {
			if ( empty( $raw[ $stage_key ] ) ) {
				continue;
			}
			
			foreach ( $raw[ $stage_key ] as $seq => $item ) {
				// Safeguard: cast plain strings (shouldn't exist now) to minimal object
				if ( ! is_array( $item ) ) {
					$item = [
						'title'       => $item,
						'description' => '',
						'polarity'    => 'positive',
						'rating'      => 3
					];
				}
				
				$out[] = [
					'label'       => $item['title'],
					'stage'       => $stage_key,
					'stage_order' => $stage_idx,
					'seq'         => $seq,
					'description' => $item['description'],
					'polarity'    => $item['polarity'],
					'rating'      => (int) $item['rating'],
				];
			}
		}
		
		return $out;
	}

       /**
        * generate_report() – 5 sequential GPT calls
        */
       public static function generate_report( ?int $post_id = null ) {
               if ( $post_id === null ) {
                       $post_id = \Jeanius\current_assessment_id();
               }

               if ( ! $post_id ) {
                       return new \WP_Error( 'login', 'Login required', [ 'status' => 401 ] );
               }

               return self::generate_report_for_post( $post_id );
       }

       /**
        * Core OpenAI/ACF logic for generating the report for a post
        */
       private static function generate_report_for_post( int $post_id ) {
               // If HTML copy fields already filled, skip regeneration
               if ( get_field( 'ownership_stakes_md_copy', $post_id ) ) {
                       return [ 'status' => 'ready' ];
               }

               $api_key = trim( (string) get_field( 'openai_api_key', 'option' ) );

               if ( empty( $api_key ) ) {
                       return new \WP_Error( 'key', 'OpenAI key missing', [ 'status' => 500 ] );
               }

               $stage_data = json_decode( get_field( 'full_stage_data', $post_id ) ?: '{}', true );

               // STEP 1 – Ownership Stakes
               $stakes_md = self::call_openai(
                       $api_key,
                       self::prompt_ownership( $stage_data )
               );

               update_field( 'ownership_stakes_md', $stakes_md, $post_id );

               // Convert $stakes_md to HTML <ul><li>...</li></ul> and save as copy
               $stakes_lines = explode( "\n", trim( $stakes_md ) );
               $stakes_title = array_shift( $stakes_lines );
               $stakes_html  = '<ul>';

               foreach ( $stakes_lines as $line ) {
                       $clean = trim( ltrim( $line, "-• \t" ) );
                       if ( ! empty( $clean ) ) {
                               $stakes_html .= '<li>' . esc_html( $clean ) . '</li>';
                       }
               }
               $stakes_html .= '</ul>';

               update_field( 'ownership_stakes_md_copy', $stakes_html, $post_id );

               // STEP 2 – Life Messages
               $life_md = self::call_openai(
                       $api_key,
                       self::prompt_life_messages( $stakes_md, $stage_data )
               );

               update_field( 'life_messages_md', $life_md, $post_id );
               update_field( 'life_messages_md_copy', $life_md, $post_id );

               // STEP 3 – Transcendent Threads
               $threads_md = self::call_openai(
                       $api_key,
                       self::prompt_threads( $stakes_md, $stage_data )
               );

               update_field( 'transcendent_threads_md', $threads_md, $post_id );
               update_field( 'transcendent_threads_md_copy', $threads_md, $post_id );

               // STEP 4 – Sum of Jeanius
               $sum_md = self::call_openai(
                       $api_key,
                       self::prompt_sum( $stakes_md, $life_md, $threads_md, $stage_data )
               );

               update_field( 'sum_jeanius_md', $sum_md, $post_id );

               // Remove the heading "### Sum of Your Jeanius" (case-insensitive, with/without extra spaces/newlines)
               $sum_md_formatted = preg_replace( '/^###\s*Sum of Your Jeanius\s*/i', '', trim( $sum_md ) );
               update_field( 'sum_jeanius_md_copy', $sum_md_formatted, $post_id );

               // STEP 4.5 – Grab colleges list from textarea
               $raw_colleges = get_field( 'target_colleges', $post_id );
               $colleges     = [];

               // Split on commas or line-breaks, trim, dedupe, keep non-empty
               if ( is_string( $raw_colleges ) ) {
                       $parts    = preg_split( '/[\r\n,]+/', $raw_colleges );
                       $colleges = array_unique( array_filter( array_map( 'trim', $parts ) ) );
               }

               // STEP 5 – College Essay Topics
               $essay_md = self::call_openai(
                       $api_key,
                       self::prompt_essays( $stakes_md, $threads_md, $stage_data, $colleges )
               );

               update_field( 'essay_topics_md', $essay_md, $post_id );
               update_field( 'essay_topics_md_copy', $essay_md, $post_id );

               // Store full raw markdown
               $full = "## Ownership Stakes\n$stakes_md\n\n" .
                       "## Life Messages\n$life_md\n\n" .
                       "## Transcendent Threads\n$threads_md\n\n" .
                       "## Sum of Your Jeanius\n$sum_md\n\n" .
                       "## College Essay Topics\n$essay_md";

               update_field( 'jeanius_report_md', $full, $post_id );

               return [ 'status' => 'ready' ];
       }

	/**
	 * Call OpenAI and return the assistant's text
	 */
	private static function call_openai( string $key, array $messages ): string {
		$resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
			'timeout' => 60,
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $key,
			],
			'body' => wp_json_encode( [
				'model'       => 'o3-mini',
				'temperature' => 0.7,
				'messages'    => $messages,
			] ),
		] );

		if ( is_wp_error( $resp ) ) {
			return '';
		}
		
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		
		return trim( $data['choices'][0]['message']['content'] ?? '' );
	}

	private static function prompt_ownership( array $data ): array {
		return [
			[
				'role'    => 'system',
				'content' => 'You are an experienced narrative guide using the Distill methodology. Think of yourself as an adventure guide helping someone explore the cave of their own life story - you have the flashlight initially, showing them the tunnels, but then you hand them the light to show you what\'s really there.

Ownership Stakes are topics where someone has deep lived experience giving them credibility and authority. These are NOT achievements or interests, but sustained patterns that have shaped who they are.

Look for:
- Patterns that span multiple life stages (not just "I was good at golf for 3 years")
- Experiences that have been lived multiple times throughout their life
- Topics that would appear repeatedly if someone watched the film of this person\'s life
- Areas where they could speak with authority on a panel based on lived experience

Remove:
- Associations (what they\'d like to be associated with)
- Affinities (what they\'re merely interested in)
- Single events or short-term activities

These can be:
- Character traits that have been tested (resilience, leadership, optimism, pessimism)
- Life experiences they\'ve navigated multiple times (adversity, isolation, change, loss)
- Skills or practices sustained over time (athletics, entrepreneurship, writing)
- Abstract concepts they\'ve wrestled with (identity, belonging, purpose)
- Specific domains they\'ve been immersed in (finances, technology, arts, caregiving)

Return 7-8 Ownership Stakes using clear, specific terms. These should be substantial topics or qualities. Can be single words, phrases, or brief descriptors like "blue-collar ethos" or "small-town sensibility."

Examples for context only (do not copy these):
- Bruce Springsteen: blue-collar ethos, small-town sensibility
- Mother Teresa: extreme compassion, dignity in death
- Rosa Parks: personal conviction, social progress

Remember: These are descriptors for topics they have the MOST life experience with across multiple stages, not just interests or single events.'
			],
			[
				'role'    => 'user',
				'content' => "Analyze these life experiences and identify 7-8 Ownership Stakes - topics where this person has sustained, deep lived experience across multiple life stages:\n\n" . wp_json_encode( $data ) . "\n\nReturn only the list of Ownership Stakes, one per line."
			]
		];
	}

	private static function prompt_life_messages( string $stakes_md, array $data ): array {
		return [
			[
				'role'    => 'system',
				'content' => 'You are helping someone articulate Life Messages - the truths they\'ve earned the right to share because they\'ve LIVED them. These are not advice or motivational quotes, but authentic insights from their actual experience.

Think of it this way: If they were on a panel about one of their ownership stakes, what could they say with genuine credibility? Not what they WANT to say, but what their life gives them authority to say.

Life Messages should:
- Be grounded in lived experience, not theory or wishful thinking
- Feel fresh and specific, avoiding clichés like "Every setback is a setup for a comeback"
- Range from practical wisdom to deeper philosophical insights
- Be memorable enough that someone might remember it 10 years later
- Sound like something a real person would actually say after living through something

Good Life Messages often:
- State hard truths simply
- Invert common wisdom
- Ask provocative questions
- Reveal paradoxes
- Use unexpected metaphors

Avoid:
- Generic self-help language
- TJ Maxx wall art phrases
- Overly poetic or pretentious language
- Clichés and platitudes

Examples of authentic tone (create entirely original messages):
- "Adversity is just an ingredient"
- "Money doesn\'t need a middleman"
- "If you\'re gonna eat shit, don\'t nibble"
- "Isolation is an underutilized practice"
- "Identity is an ongoing choice"
- "The only tired I was, was tired of giving in"

For each Ownership Stake, write one message this person has earned through their actual experience. Make each one distinct and authentic to that specific stake.

Important formatting instructions:  
- Return the result ONLY as a single valid HTML <table>.  
- Each message must be in one <tr>.  
- Each row must contain exactly 3 <td> cells:  
1) Counter number (starting at 1, include a dot e.g., "1.")  
2) Title with class="title"  
3) Message text with class="information" (with quotes) 

Do not include markdown, bullet points, or explanatory text. Only return the <table> … </table>.'
			],
			[
				'role'    => 'user',
				'content' => "Based on these Ownership Stakes and life experiences, write one powerful, authentic life message for each - something this person can credibly say based on lived experience:\n\nOwnership Stakes:\n$stakes_md\n\nLife-stage data JSON (context):\n" . wp_json_encode( $data )
			]
		];
	}

	private static function prompt_threads( string $stakes_md, array $data ): array {
		return [
			[
				'role'    => 'system',
				'content' => 'You are identifying Transcendent Threads - universal themes threaded through this person\'s life that connect them to everyone else. These are the connective tissue between this person and their audience.

The 22 Universal Threads and their deeper meanings:

1. **LOVE** - Profound presence OR absence of love. Often the absence (abuse, divorce, instability). Not common unless there\'s significant dysfunction.

2. **LOSS** - Presence of adversity across the spectrum (death, breakups, job loss, injuries, dreams lost). Very common thread.

3. **FAMILY** - Family as source of decisions/encouragement. Tight-knit core or extended family. "My grandparents mentored me every summer."

4. **HOPE** - Constantly getting back up from adversity quickly. Optimistic mentality despite setbacks. "I can do this." Often transferred from others.

5. **TRUTH** - Where truth OR its absence was a prominent teacher. "I wanted to know what was real." "Control what you can control." Often follows loss.

6. **MYSTERY** - Presence of unknown. Multiple moves, immigration, constant change. Lack of predictability. "We moved 5 times before high school."

7. **LOYALTY** - Sticking with something/someone OR someone sticking with them. Principled, stubborn. "I\'m not a quitter because I believe in this."

8. **SIMPLICITY** - Engineering minds wanting to know WHY/HOW things work. Also those fleeing chaos. "I wanted to distill it down to the essence."

9. **REDEMPTION** - Wrong done BY them or TO them that gets righted. Carrying burdens that transform. "Good came from the bad."

10. **SECURITY** - "I was really insecure." "Didn\'t know who I was." Common with young people about relational safety and belonging.

11. **TRIUMPH** - Type A personalities. "I love winning, hate losing." Achievement after achievement. The hard chargers.

12. **PROGRESS** - Resilient perseverers who keep moving forward regardless. Learners. "I learned this, then traveled there, then served here."

13. **FAITH** - Spiritual dimension as organizing principle. Trust in something greater. Can be religious or secular faith in process/people.

14. **SACRIFICE** - Others-first posture. "What we give up to gain." Often shows how they navigate challenges.

15. **GRACE** - Unearned favor received or given. Space between justice and mercy. Often follows identity work.

16. **BEAUTY** - Those who find extraordinary in ordinary. Artists, creatives, those who see differently.

17. **JOY** - Deeper than happiness. Often emerges as result of journey through other threads.

18. **IDENTITY** - "Who am I?" work. Very common in adolescents. Wrestling with self-definition.

19. **FREEDOM** - Breaking chains internal/external. Response to feeling trapped. Quest for autonomy.

20. **RESILIENCE** - Different from loyalty - no specific purpose, just "I\'m not a quitter." Getting back up because that\'s who they are.

21. **INNOVATION** - Creating new from existing. Disruptors. "I always wanted to build something different."

22. **CONTRIBUTION** - Service orientation. Legacy mindset. "To fight for others is transcendent."

Select THREE threads forming this person\'s pattern. Look for threads appearing multiple times across their timeline. Do NOT label them as Inciting/Bridge/Payoff - simply identify three that work together.

Common patterns (but find what\'s authentic to this person):
- Loss → Truth → Progress (adversity leads to wisdom leads to forward movement)
- Mystery → Progress → Joy (unknown becomes adventure becomes fulfillment)
- Identity → Grace → Simplicity (self-discovery through acceptance leads to clarity)

1. First output just the thread names inside:
<ul class="labels">
<li>[Thread Name]</li>
<li>[Thread Name]</li>
<li>[Thread Name]</li>
</ul>

2. Then output the detailed numbered explanations inside:
<div class="labels-data">
<ul>
<li>1. <span class="color-blue">[THREAD NAME]</span> [1–2 sentence explanation in 2nd person voice]</li>
<li>2. <span class="color-blue">[THREAD NAME]</span> [1–2 sentence explanation in 2nd person voice]</li>
<li>3. <span class="color-blue">[THREAD NAME]</span> [1–2 sentence explanation in 2nd person voice]</li>
</ul>
</div>

Speak directly to the user using "you" and "your." Do not use third-person language. Choose only from the 22 threads listed. Look for threads that appear multiple times across their story.'
			],
			[
				'role'    => 'user',
				'content' => "Based on these Ownership Stakes and life experiences, identify THREE Transcendent Threads that form this person\'s pattern:\n\nOwnership Stakes:\n$stakes_md\n\nLife Data:\n" . wp_json_encode( $data ) . "\n\nChoose only from the 22 threads listed. Look for threads that appear multiple times across their story."
			]
		];
	}

	private static function prompt_sum( string $stakes_md, string $life_md, string $threads_md, array $data ): array {
		return [
			[
				'role'    => 'system',
				'content' => 'Write the "Sum of Your Jeanius" - the moment when everything clicks, when scattered experiences suddenly form a constellation that makes sense. This is recognition, not analysis or advice.

Like the best moment in therapy when someone finally sees themselves clearly, help them understand:
- Why their particular combination of experiences matters
- What their life has been preparing them for (without being prescriptive)
- The unique value only they can bring
- How their threads weave into something larger

This should feel like:
- Coming home to themselves
- Finally naming what they\'ve always sensed but couldn\'t articulate
- Understanding why the hard things happened
- Seeing their life as preparation, not random events
- The affirmation their life has mattered even the bad stuff

In 4-6 powerful sentences, weave together their Ownership Stakes, Life Messages, and Transcendent Threads into a coherent understanding of who they are and why that matters.

Write with warmth, precision, and emotional intelligence. Make them feel deeply seen and understood. Use "you/your" throughout.

This is not a summary of data points. It\'s a mirror showing them the core of their identity with clarity and specificity. Like a wise mentor who has studied their life and can finally articulate the most important truths they\'ve felt but never fully named.

Don\'t mention assessments, results, or this process. Just hold up the mirror and show them who they are.'
			],
			[
				'role'    => 'user',
				'content' => "Create the Sum of Your Jeanius from these elements:\n\nOwnership Stakes:\n$stakes_md\n\nLife Messages:\n$life_md\n\nTranscendent Threads:\n$threads_md\n\nWrite a 4-6 sentence paragraph that reveals the constellation their experiences form.\n\nReturn the paragraph under the heading 'Sum of Your Jeanius'."
			]
		];
	}

	private static function prompt_essays( string $stakes_md, string $threads_md, array $data, array $colleges ): array {
		$college_line = empty( $colleges )
			? 'There are no target colleges.'
			: 'Target colleges: ' . implode( ', ', $colleges ) . '.';

		return [
			[
				'role'    => 'system',
				'content' => "You are helping a student discover essay topics that emerge naturally from their Jeanius insights. These essays should reveal character through specific moments, not list achievements.

The transcendent threads inform the tone:
- Loss/Mystery threads = more reflective, searching voice
- Progress/Triumph threads = forward-moving, aspirational voice
- Family/Loyalty threads = relationship-centered narrative
- Identity/Truth threads = self-discovery arc
- Resilience/Sacrifice threads = overcoming through letting go

{$college_line}

Create 5 DISTINCT essay topics that:
- Each draws from different Ownership Stakes or combinations
- Shows growth through specific moments, not general claims
- Reveals character qualities colleges value
- Demonstrates how their threads prepare them for contribution
- Points to who they\'re becoming, not just who they\'ve been

OUTPUT ONLY VALID HTML (no Markdown) in the following structure:

<div class=\"essay-topic no-break\">
	<p class=\"color-blue\">Topic #[number]</p>
	<h2 class=\"title\">[Specific, Intriguing Title]</h2>

	<p class=\"section-title\"><strong>Opening Scene:</strong></p>
	<p class=\"rationale-text\">[Specific moment/image that starts the essay - should drop reader into action]</p>

	<p class=\"section-title\"><strong>The Journey:</strong></p>
	<p class=\"rationale-text\">[How this moment led to insight or transformation. What you discovered about yourself or the world through this experience - 2-3 sentences in second-person voice]</p>

	<p class=\"section-title\"><strong>Key Moments to Include:</strong></p>
	<ul class=\"writing-outline\">
		<li><img class=\"bullet\" src=\"https://jeaniusdev.wpenginepowered.com/wp-content/themes/jeanius/assets/images/star-li.png\">Opening scene that creates immediate engagement</li>
		<li><img class=\"bullet\" src=\"https://jeaniusdev.wpenginepowered.com/wp-content/themes/jeanius/assets/images/star-li.png\">The challenge, question, or tension you faced</li>
		<li><img class=\"bullet\" src=\"https://jeaniusdev.wpenginepowered.com/wp-content/themes/jeanius/assets/images/star-li.png\">The turning point or moment of realization</li>
		<li><img class=\"bullet\" src=\"https://jeaniusdev.wpenginepowered.com/wp-content/themes/jeanius/assets/images/star-li.png\">How this changed your approach or understanding</li>
		<li><img class=\"bullet\" src=\"https://jeaniusdev.wpenginepowered.com/wp-content/themes/jeanius/assets/images/star-li.png\">Connection to who you\'ll be in college</li>
	</ul>

	<p class=\"section-title\"><strong>Why This Works:</strong></p>
	<p class=\"rationale-text\">[1-2 sentences on what character qualities this reveals and why admissions officers would connect with it]</p>

	<p class=\"section-title\"><strong>College Connections:</strong></p>
	<ul class=\"tailoring-tips\">
		<li><span class=\"college-name\">[College Name]</span> - [Specific program, value, opportunity, or aspect of campus culture this connects to]</li>
		<!-- Repeat for each target college -->
	</ul>
</div>

Remember: The best college essays focus on small moments that reveal big truths. They show rather than tell. They\'re specific enough to be memorable but universal enough to resonate.

Avoid:
- Generic topics (the big game, mission trip, divorced parents without unique angle)
- Topics about others more than yourself
- Trying to impress rather than connect
- Abstract philosophizing without concrete examples
- Lists of achievements disguised as narrative

STRICT RULES:
- Always output exactly 5 <div class=\"essay-topic\"> blocks.
- College Connections list appears only if there are target colleges; otherwise, omit that section entirely.
- College names should match those provided; do not invent new ones.
- Speak in second person (\"you\") when explaining rationale and journey.
- No Markdown syntax, only HTML."
			],
			[
				'role'    => 'user',
				'content' => "Create 5 distinct essay topics from:\n\nOwnership Stakes:\n$stakes_md\n\nTranscendent Threads:\n$threads_md\n\nLife Experiences:\n" . wp_json_encode( $data ) . "\n\nEach topic should reveal different aspects of character and draw from different combinations of their stakes and threads."
			]
		];
	}

	/**
	 * Parse the GPT markdown into sections and save each ACF field.
	 */
	private static function save_report_sections( int $post_id, string $md ): void {
		// Always keep the raw Markdown
		update_field( 'jeanius_report_md', $md, $post_id );

		// Split Markdown into sections (case-insensitive)
		preg_match_all(
			'/^##\s+(.+?)\s*$\R([\s\S]+?)(?=^##\s|\z)/mi',
			$md, 
			$m, 
			PREG_SET_ORDER
		);

		$sections = [];
		foreach ( $m as $match ) {
			$sections[ strtolower( trim( $match[1] ) ) ] = trim( $match[2] );
		}

		// Simple map header → textarea + wysiwyg field
		$map = [
			'ownership stakes'      => [ 'ownership_stakes_md',      'ownership_stakes_md_copy' ],
			'life messages'         => [ 'life_messages_md',         'life_messages_md_copy' ],
			'transcendent threads'  => [ 'transcendent_threads_md',  'transcendent_threads_md_copy' ],
			'sum of your jeanius'   => [ 'sum_jeanius_md',           'sum_jeanius_md_copy' ],
			'college essay topics'  => [ 'essay_topics_md',          'essay_topics_md_copy' ],
		];

		foreach ( $map as $header => [ $md_field, $html_field ] ) {
			if ( ! isset( $sections[ $header ] ) ) {
				continue;
			}

			// Save raw markdown
			update_field( $md_field, $sections[ $header ], $post_id );

			// Convert to HTML via OpenAI & save into WYSIWYG field
			$html = self::markdown_to_html( $sections[ $header ] );
			update_field( $html_field, $html, $post_id );
		}
	}

	/**
	 * Convert a single Markdown block to semantic HTML through OpenAI.
	 * Returns plain HTML (no enclosing <html> / <body> tags).
	 */
	private static function markdown_to_html( string $markdown ): string {
		$api_key = trim( (string) get_field( 'openai_api_key', 'option' ) );
		
		if ( ! $api_key ) {
			return $markdown; // fallback: leave md unchanged
		}

		$body = [
			'model'       => 'o3-mini',
			'max_tokens'  => 2048,
			'temperature' => 0,
			'messages'    => [
				[ 
					'role'    => 'system', 
					'content' => 'You are a Markdown to HTML converter. Return ONLY valid HTML inside <section> without additional commentary.' 
				],
				[ 
					'role'    => 'user', 
					'content' => $markdown 
				]
			],
		];

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			[
				'timeout' => 40,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key
				],
				'body' => wp_json_encode( $body )
			]
		);

		if ( is_wp_error( $response ) ) {
			return $markdown;
		}

		$parsed = json_decode( wp_remote_retrieve_body( $response ), true );
		
		return $parsed['choices'][0]['message']['content'] ?? $markdown;
	}
}