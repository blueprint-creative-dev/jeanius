<?php
namespace Jeanius;

class Rest {

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes() {

		register_rest_route( 'jeanius/v1', '/stage', [
			'methods'             => 'POST',
			'permission_callback' => function () { return is_user_logged_in(); },
			'callback'            => [ __CLASS__, 'save_stage' ],
		] );
		register_rest_route( 'jeanius/v1', '/review', [
			'methods'             => 'POST',
			'permission_callback' => function(){ return is_user_logged_in(); },
			'callback'            => [ __CLASS__, 'save_order' ],
		] );
		/* ---------- save description for one word ---------- */
register_rest_route( 'jeanius/v1', '/describe', [
    'methods'             => 'POST',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback'            => [ __CLASS__, 'save_description' ],
] );

// ────────────────────────────────
// Generate Jeanius report (OpenAI)
// POST /wp-json/jeanius/v1/generate
// ────────────────────────────────
register_rest_route( 'jeanius/v1', '/generate', [
	'methods'             => 'POST',
	'permission_callback' => fn() => is_user_logged_in(),
	'callback'            => [ __CLASS__, 'generate_report' ],
] );


	}

	public static function save_stage( \WP_REST_Request $r ) {

		$post_id   = \Jeanius\current_assessment_id();          // ✔ always get my post
		if ( ! $post_id ) {
			return new \WP_Error( 'login_required', 'Login first', [ 'status'=>401 ] );
		}

		$stage_key = sanitize_text_field( $r->get_param( 'stage' ) );
		$entries   = $r->get_param( 'entries' );

		if ( ! $stage_key || empty( $entries ) ) {
			return new \WP_Error( 'missing', 'Missing data', [ 'status'=>400 ] );
		}

		$data = json_decode( get_field( 'stage_data', $post_id ) ?: '{}', true );
		$data[ $stage_key ] = array_values(
			array_filter( array_map( 'sanitize_text_field', $entries ) )
		);

		\update_field( 'stage_data', wp_json_encode( $data ), $post_id );

		return [ 'success' => true ];
	}
	/** ------------------------------------------------------------------
 * Save reordered words during 5-minute review
 * POST /jeanius/v1/review
 * Body: { "ordered": { "early_childhood":[...], "elementary":[...] ... } }
 * ------------------------------------------------------------------*/
public static function save_order( \WP_REST_Request $r ) {

	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) {
		return new \WP_Error( 'login', 'Login required', [ 'status'=>401 ] );
	}

	$ordered = $r->get_param( 'ordered' );
	if ( ! is_array( $ordered ) ) {
		return new \WP_Error( 'bad', 'Missing ordered data', [ 'status'=>400 ] );
	}

	\update_field( 'stage_data', wp_json_encode( $ordered ), $post_id );
	return [ 'success' => true ];
} 

// helper to read / write progress count
private static function stage_counter( int $post_id, string $stage, ?int $set = null ) {
    $key = "_{$stage}_done";
    if ( $set !== null ) {
        update_post_meta( $post_id, $key, $set );
    }
    return (int) get_post_meta( $post_id, $key, true );
}


public static function save_description( \WP_REST_Request $r ){

    $post_id = \Jeanius\current_assessment_id();
    if( ! $post_id ) return new \WP_Error('login','Login', ['status'=>401]);

    $stage   = sanitize_text_field( $r['stage'] );
    $index   = (int) $r['index'];
    $desc    = sanitize_textarea_field( $r['description'] );
    $pol     = $r['polarity'] === 'negative' ? 'negative' : 'positive';
    $rating  = min(5,max(1,(int)$r['rating']));

    /* --------- append to full_stage_data ------------ */
    $full = json_decode( get_field('full_stage_data',$post_id) ?: '{}', true );
    $full[$stage][] = [
        'title'       => $r['title'],
        'description' => $desc,
        'polarity'    => $pol,
        'rating'      => $rating,
    ];
    update_field( 'full_stage_data', wp_json_encode( $full ), $post_id );

    /* --------- bump progress counter ---------------- */
    $done = self::stage_counter( $post_id, $stage );
    self::stage_counter( $post_id, $stage, $done + 1 );

    return ['success'=>true];
}


public static function get_timeline_data( int $post_id ) : array {

	$raw  = json_decode( get_field( 'full_stage_data', $post_id ) ?: '{}', true );
	$out  = [];
	$order = [ 'early_childhood', 'elementary', 'middle_school', 'high_school' ];
	foreach ( $order as $stage_idx => $stage_key ) {
		if ( empty( $raw[$stage_key] ) ) continue;
		foreach ( $raw[$stage_key] as $seq => $item ) {
			// safeguard: cast plain strings (shouldn’t exist now) to minimal object
			if ( ! is_array( $item ) ) {
				$item = [ 'title'=>$item, 'description'=>'', 'polarity'=>'positive', 'rating'=>3 ];
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
/* --------------------------------------------------------------
 * generate_report() – 5 sequential GPT calls
 * --------------------------------------------------------------*/
public static function generate_report( \WP_REST_Request $r ) {

	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) return new \WP_Error( 'login', 'Login required', [ 'status'=>401 ] );

	// If HTML copy fields already filled, skip regeneration
	if ( get_field( 'ownership_stakes_md_copy', $post_id ) ) {
		return [ 'status' => 'ready' ];
	}

	$api_key = trim( (string) get_field( 'openai_api_key', 'option' ) );
	if ( empty( $api_key ) ) return new \WP_Error( 'key', 'OpenAI key missing', [ 'status'=>500 ] );

	$stage_data = json_decode( get_field( 'full_stage_data', $post_id ) ?: '{}', true );

	/* ---------- STEP 1 ─ Ownership Stakes ---------- */
	$stakes_md = self::call_openai(
		$api_key,
		self::prompt_ownership( $stage_data )
	);
	update_field( 'ownership_stakes_md',      $stakes_md, $post_id );
	update_field( 'ownership_stakes_md_copy', $stakes_md, $post_id );

	/* ---------- STEP 2 ─ Life Messages ------------ */
	$life_md = self::call_openai(
		$api_key,
		self::prompt_life_messages( $stakes_md )
	);
	update_field( 'life_messages_md',      $life_md, $post_id );
	update_field( 'life_messages_md_copy', $life_md, $post_id );

	/* ---------- STEP 3 ─ Transcendent Threads ----- */
	$threads_md = self::call_openai(
		$api_key,
		self::prompt_threads( $stakes_md, $stage_data )
	);
	update_field( 'transcendent_threads_md',      $threads_md, $post_id );
	update_field( 'transcendent_threads_md_copy', $threads_md, $post_id );

	/* ---------- STEP 4 ─ Sum of Jeanius ---------- */
	$sum_md = self::call_openai(
		$api_key,
		self::prompt_sum( $stakes_md, $life_md, $threads_md )
	);
	update_field( 'sum_jeanius_md',      $sum_md, $post_id );
	update_field( 'sum_jeanius_md_copy', $sum_md, $post_id );

	/* STEP 4.5 – Grab colleges list from textarea */
	$raw_colleges = get_field( 'target_colleges', $post_id );  // textarea string

	$colleges = [];

	// split on commas or line-breaks, trim, dedupe, keep non-empty
	if ( is_string( $raw_colleges ) ) {
		$parts = preg_split( '/[\r\n,]+/', $raw_colleges );
		$colleges = array_unique( array_filter( array_map( 'trim', $parts ) ) );
	}

	/* now $colleges is a clean array */


	/* ---------- STEP 5 ─ College Essay Topics ----- */
	$essay_md = self::call_openai(
		$api_key,
		self::prompt_essays( $stakes_md, $threads_md, $stage_data, $colleges )
	);
	
	update_field( 'essay_topics_md',      $essay_md, $post_id );
	update_field( 'essay_topics_md_copy', $essay_md, $post_id );

	/* ---------- Store full raw markdown ------------ */
	$full = "## Ownership Stakes\n$stakes_md\n\n".
	        "## Life Messages\n$life_md\n\n".
	        "## Transcendent Threads\n$threads_md\n\n".
	        "## Sum of Your Jeanius\n$sum_md\n\n".
	        "## College Essay Topics\n$essay_md";
	update_field( 'jeanius_report_md', $full, $post_id );

	return [ 'status' => 'ready' ];
}

/** Call OpenAI and return the assistant’s text */
private static function call_openai( string $key, array $messages ) : string {

	$resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
		'timeout' => 60,
		'headers' => [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $key,
		],
		'body' => wp_json_encode( [
			'model'       => 'gpt-4o-mini',
			'temperature' => 0.7,
			'messages'    => $messages,
		] ),
	] );

	if ( is_wp_error( $resp ) ) return '';
	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	return trim( $data['choices'][0]['message']['content'] ?? '' );
}



private static function prompt_ownership( array $data ) : array {
	return [
	  ['role'=>'system','content'=>'You are a storytelling and identity analysis expert. The user will input structured data about key life events by life stage. Analyze all events, weighing emotional intensity (rating), emotional direction (polarity), and themes in the description. Extract the 7 most dominant life experience categories and return them as "Ownership Stakes"—areas where the user holds deep lived experience and credibility.

	Use **general categories only** (e.g., “Friendship,” “Resilience,” “Independence,” “Grief”). Avoid redundancy and do not combine multiple concepts in a single stake. Do not use narrative or story-like phrasing.

	Speak directly to the user using “you” or “your” if needed. Do not include any fictional names or characters from previous assessments in the output. The examples below are provided strictly for format and tone reference only—they must not appear in your answer unless it directly applies to the users story.

	*Examples of ownership stakes from other well-known narratives:
	Example 1: Bruce Springsteen owns…
	• blue-collar ethos
	• small-town sensibility
	Example 2: Mother Teresa owns…
	• extreme compassion
	• dignity in death
	Example 3: Abe Lincoln owns…
	• human equality
	• personal character
	Example 4: Rosa Parks owns…
	• personal conviction
	• social progress*'],
	  ['role'=>'user',  'content'=>"Here is the data:\n\n".wp_json_encode($data)."\n\nReturn only:\nOwnership Stakes: [list of 7 key categories]"]
	];
}


private static function prompt_life_messages( string $stakes_md ) : array {
	return [
	  ['role'=>'system','content'=>'You are a storytelling coach. Based on the following Ownership Stakes, write one powerful, grounded life message for each—something the user can credibly say based on lived experience.

		Each message should be emotionally resonant and phrased like a truth statement. Aim for short to medium-length—not too poetic. Avoid clichés. Keep the tone raw, personal, and real. 

		Use the examples below only as inspiration for tone and structure. Do not mention the name Steph Hauser or reuse any of these specific example phrases in your output.

		*The messages listed below are riffs off Ownership Stakes from a fictional assessment. These are phrases that the person’s narrative gives them credibility to speak.

		Steph Hauser’s life says…
		• On family dysfunction: “That story is not your story.”
		• On adventure: “Say yes to the big thing.”
		• On freedom: “Break those chains.”
		• On addiction and recovery: “There’s help and hope for you.”
		• On parenting: “Just hold on and let go.”
		• On conflict: “What is truly in conflict?”
		• On surrender: “It is where the miracle becomes possible.”
		• On athletics: “You can do more than you think you can.”
		• On community: “Get in here.”*'],
	  
	  ['role'=>'user',  'content'=>"Ownership Stakes:\n$stakes_md"]
	];
}


private static function prompt_threads( string $stakes_md, array $data ) : array {
	return [
	  ['role'=>'system','content'=>'You are a narrative development expert. Based on the provided life events and ownership stakes, identify the individual’s Transcendent Pattern.

		Select **one Confrontation Thread**, **one Bridge Thread**, and **one Payoff Thread** from the following universal list of 22 Transcendent Threads:

		1. Love  
		2. Loss  
		3. Family  
		4. Hope  
		5. Truth  
		6. Mystery  
		7. Loyalty  
		8. Simplicity  
		9. Redemption  
		10. Security  
		11. Triumph  
		12. Progress  
		13. Faith  
		14. Sacrifice  
		15. Grace  
		16. Beauty  
		17. Joy  
		18. Identity  
		19. Freedom  
		20. Resilience  
		21. Innovation  
		22. Contribution  

		You must choose **only from this list**. Do not invent your own.

		Your goal is to help the user understand the deeper pattern in their life story. Label each thread clearly using the format:

		[Transcendent Pattern Name]
		Thread #1 (“Inciting Thread”): [Thread Name]  
		• [1–2 sentence explanation in 2nd person voice]

		Thread #2 (“Bridge Thread”): [Thread Name]  
		• [1–2 sentence explanation in 2nd person voice]

		Thread #3 (“Payoff Thread”): [Thread Name]  
		• [1–2 sentence explanation in 2nd person voice]

		Speak directly to the user using “you” and “your.” Do not use third-person language. Do not reuse the names or content from the fictional example below. It is for formatting and tone reference only.

		*Example (do not copy):
		Steph Hauser’s life follows the Transcendent Pattern:  
		Mystery >>> Resilience >>> Truth

		Thread #1 (“Inciting Thread”): MYSTERY  
		• You view the unknowns of life—good and bad—as adventures worth embracing; as a result, your life inspires and invites others to take the same approach.

		Thread #2 (“Bridge Thread”): RESILIENCE  
		• You possess a tenacious posture that compels you to see decisions and circumstances through, for the benefit of you and your tribe.

		Thread #3 (“Payoff Thread”): TRUTH  
		• You are rewarded by the discovery of deeper realities and truths, for yourself and others, that result from a life of bravery and determination.*'],
	  
	  ['role'=>'user',  'content'=>"Ownership Stakes:\n$stakes_md\n\nHere is the data:\n\n".wp_json_encode($data)]
	];
}


private static function prompt_sum( string $stakes_md, string $life_md, string $threads_md ) : array {
	return [
	  ['role'=>'system','content'=>'You are a deeply insightful narrative guide. Your job is to help someone see themselves clearly by reflecting back the core threads of who they are.

Using the user’s Ownership Stakes, Life Messages, and Transcendent Threads, write a short but powerful paragraph that synthesizes the emotional and motivational throughlines of their story. Speak directly to the user using “you” and “your.” Your tone should be affirming, emotionally intelligent, and personal; like a wise mentor who has studied their life and is now articulating the most important truths they might have felt but never fully named.

Rather than summarizing data points, weave them together into a cohesive insight that reveals:
- what environments they thrive in,
- what fuels their sense of meaning and purpose,
- and what kinds of communities or causes stir something deep in them.

This paragraph should be the heart of the whole document. It’s not advice. It’s not a summary. It’s a mirror showing the user the core of their identity with warmth, clarity, and specificity.

Keep it to 4–6 sentences. Do not refer to “this assessment” or “these results.” Just speak to the user directly.'],

	  ['role'=>'user','content'=>"Ownership Stakes: [$stakes_md]\nLife Messages: [$life_md]\nTranscendent Threads: [$threads_md]\n\nReturn the paragraph under the heading 'Sum of Your Jeanius'."]
	];
}



private static function prompt_essays( string $stakes_md,
                                       string $threads_md,
                                       array  $data,
                                       array  $colleges ) : array {

    $college_line = empty($colleges)
        ? 'There are no target colleges.'
        : 'Target colleges: '.implode(', ', $colleges).'.';

    return [
      [
        'role'=>'system',
        'content'=>"You are a college-essay strategist. Use ONLY the colleges the user provided when you write tailoring tips. NEVER invent additional schools.

${college_line}

Write five distinct essay topic suggestions that follow the **StoryBrand narrative arc**, where the student is the **hero of the story**, and their experiences represent:

1. A character (you)  
2. With a problem  
3. Who meets a guide or gains insight  
4. Is given a plan  
5. Is called to action  
6. Faces stakes (what could go wrong)  
7. And ultimately envisions success

For each of five essay topics include:
1. Title  
2. Rationale (2–3 sentences, second-person voice, future-oriented)  
3. Writing outline (5 bullets, each step should advance the narrative arc and lead to what kind of student/person you'll be in college and beyond)  
4. Tailoring Tips – one sub-bullet per target college above.

The experiences you’re drawing from are in the past, but the essays should point to how those moments prepare you for what’s ahead. Help the reader see how your story continues on their campus.

Make sure all topics are distinct. You may include one wildcard or creative topic that reflects a surprising or joyful part of their story.

Speak in second person (“you”) when explaining rationale or structure. Do not use third-person language. Do not reference any fictional names or previous assessments."
      ],
      [
        'role'=>'user',
        'content'=>"Ownership Stakes:\n$stakes_md\n\n"
                 ."Transcendent Threads:\n$threads_md\n\n"
                 ."Life-stage data JSON:\n".wp_json_encode($data)
      ]
    ];
}





/**
 * Parse the GPT markdown into sections and save each ACF field.
 */
private static function save_report_sections( int $post_id, string $md ) : void {

	// Always keep the raw Markdown
	update_field( 'jeanius_report_md', $md, $post_id );

	/* 1 ─ split Markdown into sections (case-insensitive) */
	preg_match_all(
		'/^##\s+(.+?)\s*$\R([\s\S]+?)(?=^##\s|\z)/mi',
		$md, $m, PREG_SET_ORDER
	);

	$sections = [];
	foreach ( $m as $match ) {
		$sections[ strtolower( trim( $match[1] ) ) ] = trim( $match[2] );
	}

	/* 2 ─ simple map header → textarea + wysiwyg field */
	$map = [
		'ownership stakes'      => ['ownership_stakes_md',      'ownership_stakes_md_copy'],
		'life messages'         => ['life_messages_md',         'life_messages_md_copy'],
		'transcendent threads'  => ['transcendent_threads_md',  'transcendent_threads_md_copy'],
		'sum of your jeanius'   => ['sum_jeanius_md',           'sum_jeanius_md_copy'],
		'college essay topics'  => ['essay_topics_md',          'essay_topics_md_copy'],
	];

	foreach ( $map as $header => [$md_field,$html_field] ) {
		if ( ! isset( $sections[ $header ] ) ) continue;

		// 2a  save raw markdown
		update_field( $md_field, $sections[ $header ], $post_id );

		// 2b  convert to HTML via OpenAI & save into WYSIWYG field
		$html = self::markdown_to_html( $sections[ $header ] );
		update_field( $html_field, $html, $post_id );
	}
}

/**
 * Convert a single Markdown block to semantic HTML through OpenAI.
 * Returns plain HTML (no enclosing <html> / <body> tags).
 */
private static function markdown_to_html( string $markdown ) : string {

	$api_key = trim( (string) get_field( 'openai_api_key', 'option' ) );
	if ( ! $api_key ) return $markdown;   // fallback: leave md unchanged

	$body = [
		'model'     => 'gpt-4o-mini',
		'max_tokens'=> 2048,
		'temperature'=> 0,
		'messages'  => [
			[ 'role'=>'system', 'content'=>'You are a Markdown to HTML converter. Return ONLY valid HTML inside <section> without additional commentary.' ],
			[ 'role'=>'user',   'content'=> $markdown ]
		],
	];

	$response = wp_remote_post(
		'https://api.openai.com/v1/chat/completions',
		[
			'timeout'=>40,
			'headers'=>[
				'Content-Type'=>'application/json',
				'Authorization'=>'Bearer '.$api_key
			],
			'body'=> wp_json_encode( $body )
		]
	);

	if ( is_wp_error( $response ) ) return $markdown;

	$parsed = json_decode( wp_remote_retrieve_body( $response ), true );
	return $parsed['choices'][0]['message']['content'] ?? $markdown;
}



}