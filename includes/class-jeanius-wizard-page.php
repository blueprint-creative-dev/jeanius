<?php
/**
 * Front-end “wizard” page that drives the 15-minute timeline flow.
 */
namespace Jeanius;

class Wizard_Page {

	/**
	 * Outputs a bare-bones HTML page (bypasses theme templates).
	 * URL carries ?post={assessment_ID}
	 */
	public static function render() {

		$post_id = \Jeanius\current_assessment_id();
		if ( ! $post_id ) wp_die( 'Please log in first.' );

		$rest_nonce = wp_create_nonce( 'wp_rest' );
		?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?php wp_head(); ?>
    <style>
    
    </style>
</head>

<body <?php body_class(); ?>>
	<section class="step-assessment step step-3">
		<div class="container">
			<div class="head-logo">
    			<img src="<?php echo plugins_url( 'jeanius/public/images/logo.png' ); ?>" alt="Logo" class="logo-image img-fluid">
    		</div>
        	<div class="form-head">
        		<h3>Early Childhood</h3>
        		<h4>(0 - 5 years old)</h4>
        	</div>
    		<div class="content">
		        <p><span class="color-orange">:05 Minute timer begins after first answer...</span>
		        Add each milestone as a brief description on its own line. (e.g.  “1st Pet” > “Traumatic Stitches”) Do not worry about chronological order. </p>
		    </div>
    		<div class="form-wrap">
                <div class="timer-wrap">
                    <svg class="progress-ring">
                      <circle class="ring-bg" r="54" cx="75" cy="75" />
                      <circle class="ring-fill" r="54" cx="75" cy="75" />
                    </svg>
                    <div class="inner-ring">
                      <div id="timer">5</div>
                      <span>min</span>
                    </div>
                  </div>
    			<textarea id="entries" rows="8" placeholder="1st Pet
Traumatic Stitches"></textarea>
            </div>
	        <div class="cta-wrapper">
	        	<button id="save-btn" class="button button-primary" disabled>Continue</button>
	        </div>
	        <div class="progress">
	            <ul>
	                <li class="active">Early Childhood</li>
	                <li>Elementary</li>
	                <li>Middle</li>
	                <li>High School</li>
	            </ul>
        	</div>
		</div>
	</section>
    <script>
    /* ---------- 5-minute countdown ---------- */

jQuery(function () {
  const totalTime = 5 * 60; // 5 minutes in seconds
  const circle = jQuery(".ring-fill");
  const radius = circle.attr("r");
  const circumference = 2 * Math.PI * radius;
  let elapsed = 0;
  let timerStarted = false;
  let timerInterval = null;

  circle.css({
    "stroke-dasharray": circumference,
    "stroke-dashoffset": circumference // start empty
  });

  function updateTimer() {
    const remaining = totalTime - elapsed;
    const minutes = Math.floor(remaining / 60);
    const seconds = remaining % 60;

    if (remaining > 0) {
      if (remaining >= 60) {
        jQuery("#timer").text(`${minutes}`);
        jQuery("#timer").next("span").text("min");
      } else {
        jQuery("#timer").text(`${seconds}`);
        jQuery("#timer").next("span").text("sec");
      }

      const offset = circumference - (elapsed / totalTime) * circumference;
      circle.css("stroke-dashoffset", offset);

      elapsed++; // increment at the end for next cycle
    } else {
      clearInterval(timerInterval);
      jQuery("#entries").prop("disabled", true);

      // Auto-click the save button if textarea is not empty
      const ta = document.getElementById('entries');
      if (ta.value.trim().length > 0) {
        document.getElementById('save-btn').click();
      }
    }
  }

  function startTimer() {
    if (!timerStarted) {
      timerStarted = true;
      timerInterval = setInterval(updateTimer, 1000);
    }
  }

  // Initialize display
  updateTimer();

  // Start timer on first input
  jQuery("#entries").on("input", function () {
    startTimer();
  });
});


    //limit 45char
    document.getElementById('entries').addEventListener('input', function (e) {
      const maxCharsPerLine = 45;
        let lines = e.target.value.split('\n');
        // Trim each line to max 45 characters
        lines = lines.map(line => line.slice(0, maxCharsPerLine));

        // Set the new trimmed value back
        e.target.value = lines.join('\n');
    });
    //end limit 45char

    /* Enable button only when text entered */
    const ta = document.getElementById('entries');
    const btn = document.getElementById('save-btn');
    ta.addEventListener('input', () => btn.disabled = !ta.value.trim());

    /* Save stage data via REST */
    btn.addEventListener('click', async () => {
        const lines = ta.value.split(/\r?\n/).map(t => t.trim()).filter(Boolean);
        btn.disabled = true;
        btn.textContent = 'Saving…';

        const res = await fetch('<?php echo esc_url( rest_url( 'jeanius/v1/stage' ) ); ?>', {
            method: 'POST',
            credentials: 'same-origin', // send cookies
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo esc_js( $rest_nonce ); ?>' // ← nonce here
            },
            body: JSON.stringify({
                stage: 'early_childhood', // or 'elementary'
                entries: lines
            })
        }).then(r => r.json());



        if (res.success) {
            // alert('Early Childhood saved! (Next stage coming soon)');
            location.href = '/jeanius-assessment/wizard-stage-2/';

        } else {
            alert('Error – please try again');
            btn.disabled = false;
            btn.textContent = 'Save & Continue';
        }
    });
    </script>

    <?php wp_footer(); ?>
</body>

</html>


<?php
	}
/** Stage 2 – Elementary School */
public static function render_stage_two() {

    $post_id = \Jeanius\current_assessment_id();
    if ( ! $post_id ) wp_die( 'Please log in first.' );

    /* NEW — create a REST nonce for this page */
    $rest_nonce = wp_create_nonce( 'wp_rest' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<?php wp_head(); ?>
<style>

</style>
</head>

<body <?php body_class(); ?>>
    <section class="step-assessment step step-4">
    	<div class="container">
           <div class="head-logo">
    			<img src="<?php echo plugins_url( 'jeanius/public/images/logo.png' ); ?>" alt="Logo" class="logo-image img-fluid">
    		</div>
    		<div class="form-head">
    			<h3>Elementary School</h3>
        		<h4>(6-10 years old)</h4>
    		</div>
        	<div class="content">
    			<p><span class="color-orange">:05 Minute timer begins after first answer...</span>
        		Add each milestone as a brief description on its own line. (e.g.  “Bullied” > Won Spelling Bee) Do not worry about chronological order. </p>
        	</div>
            <div class="form-wrap">
                 <div class="timer-wrap">
                    <svg class="progress-ring">
                      <circle class="ring-bg" r="54" cx="75" cy="75" />
                      <circle class="ring-fill" r="54" cx="75" cy="75" />
                    </svg>
                    <div class="inner-ring">
                      <div id="timer">5</div>
                      <span>min</span>
                    </div>
                  </div>
    			<textarea id="entries" rows="8" placeholder="Bullied
Won Spelling Bee"></textarea>
            </div>
		<div class="cta-wrapper">
        	<button id="save-btn" class="button button-primary" disabled>Continue</button>
        </div>
	        <div class="progress">
	            <ul>
	                <li class="active">Early Childhood</li>
	                <li class="active">Elementary</li>
	                <li>Middle</li>
	                <li>High School</li>
	            </ul>
	        </div>
	    </div>
	</section>
    <script>
    jQuery(function () {
    const totalTime = 5 * 60; // 5 minutes in seconds
    const circle = jQuery(".ring-fill");
    const radius = circle.attr("r");
    const circumference = 2 * Math.PI * radius;
    let elapsed = 0;
    let timerStarted = false;
    let timerInterval = null;

    circle.css({
      "stroke-dasharray": circumference,
      "stroke-dashoffset": circumference // start empty
    });

    function updateTimer() {
      const remaining = totalTime - elapsed;
      const minutes = Math.floor(remaining / 60);
      const seconds = remaining % 60;

      if (remaining > 0) {
        if (remaining >= 60) {
          jQuery("#timer").text(`${minutes}`);
          jQuery("#timer").next("span").text("min");
        } else {
          jQuery("#timer").text(`${seconds}`);
          jQuery("#timer").next("span").text("sec");
        }

        const offset = circumference - (elapsed / totalTime) * circumference;
        circle.css("stroke-dashoffset", offset);

        elapsed++; // increment at the end for next cycle
      } else {
        clearInterval(timerInterval);
        jQuery("#entries").prop("disabled", true);

        // Auto-click the save button if textarea is not empty
        const ta = document.getElementById('entries');
        if (ta.value.trim().length > 0) {
          document.getElementById('save-btn').click();
        }
      }
    }

    function startTimer() {
      if (!timerStarted) {
        timerStarted = true;
        timerInterval = setInterval(updateTimer, 1000);
      }
    }

    // Initialize display
    updateTimer();

    // Start timer on first input
    jQuery("#entries").on("input", function () {
      startTimer();
    });
  });

    //limit 45char
    document.getElementById('entries').addEventListener('input', function (e) {
      const maxCharsPerLine = 45;
        let lines = e.target.value.split('\n');
        // Trim each line to max 45 characters
        lines = lines.map(line => line.slice(0, maxCharsPerLine));

        // Set the new trimmed value back
        e.target.value = lines.join('\n');
    });
    //End limit 45char
    const ta = document.getElementById('entries'),
        btn = document.getElementById('save-btn');
    ta.addEventListener('input', () => btn.disabled = !ta.value.trim());

    btn.addEventListener('click', async () => {
        const lines = ta.value.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
        btn.disabled = true;
        btn.textContent = 'Saving…';
        const res = await fetch('<?php echo esc_url( rest_url( 'jeanius/v1/stage' ) ); ?>', {
            method: 'POST',
            credentials: 'same-origin', // ← send cookies
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo esc_js( $rest_nonce ); ?>' // ← nonce
            },
            body: JSON.stringify({
                stage: 'elementary', // this stage’s key
                entries: lines
            })
        }).then(r => r.json());

        if (res.success) {
            location.href = '/jeanius-assessment/wizard-stage-3/';
        } else {
            //alert('Error, try again');
            btn.disabled = false;
            btn.textContent = 'Save & Continue';
        }
    });
    </script>
    <?php wp_footer(); ?>
</body>

</html><?php
}

/** ------------------------------------------------------------------
 *  Stage 3 – Middle School / Junior High (11 – 14)
 * ------------------------------------------------------------------*/
public static function render_stage_three() {

	// Ensure the user is logged in and has an assessment post
	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) {
		wp_die( 'Please log in first.' );
	}

	// Nonce for REST security
	$rest_nonce = wp_create_nonce( 'wp_rest' );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?php wp_head(); ?>
    <style>
    
    </style>
</head>

<body <?php body_class(); ?>>

     <section class="step-assessment step step-5">
     	<div class="container">
			<div class="head-logo">
        		<img src="<?php echo plugins_url( 'jeanius/public/images/logo.png' ); ?>" alt="Logo" class="logo-image img-fluid">
            </div>
            <div class="form-head">
			    <h3>Middle School</h3>
			    <h4>(11 - 14 years old)</h4>
			</div>
			<div class="content">
			    <p><span class="color-orange">:05 Minute timer begins after first answer...</span>
			    Add each milestone as a brief description on its own line. (e.g.  “1st Pet” > “Traumatic Stitches”) Do not worry about chronological order. </p>
			</div>
            <div class="form-wrap">
                  <div class="timer-wrap">
                    <svg class="progress-ring">
                      <circle class="ring-bg" r="54" cx="75" cy="75" />
                      <circle class="ring-fill" r="54" cx="75" cy="75" />
                    </svg>
                    <div class="inner-ring">
                      <div id="timer">5</div>
                      <span>min</span>
                    </div>
                  </div>
    			<textarea id="entries" rows="8" placeholder="1st Pet
Traumatic Stitches"></textarea>
            </div>
	    <div class="cta-wrapper">
	    	<button id="save-btn" class="button button-primary" disabled>Continue</button>
	    </div>
		    <div class="progress">
		        <ul>
		            <li class="active">Early Childhood</li>
		            <li class="active">Elementary</li>
		            <li class="active">Middle</li>
		            <li>High School</li>
		        </ul>
	    	</div>
	    </div>
	</section>
    <script>
    /* ---------- 15-minute countdown ---------- */
    /* timer identical to stage 1 */
    jQuery(function () {
      const totalTime = 5 * 60; // 5 minutes in seconds
      const circle = jQuery(".ring-fill");
      const radius = circle.attr("r");
      const circumference = 2 * Math.PI * radius;
      let elapsed = 0;
      let timerStarted = false;
      let timerInterval = null;

      circle.css({
        "stroke-dasharray": circumference,
        "stroke-dashoffset": circumference // start empty
      });

      function updateTimer() {
        const remaining = totalTime - elapsed;
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;

        if (remaining > 0) {
          if (remaining >= 60) {
            jQuery("#timer").text(`${minutes}`);
            jQuery("#timer").next("span").text("min");
          } else {
            jQuery("#timer").text(`${seconds}`);
            jQuery("#timer").next("span").text("sec");
          }

          const offset = circumference - (elapsed / totalTime) * circumference;
          circle.css("stroke-dashoffset", offset);

          elapsed++; // increment at the end for next cycle
        } else {
          clearInterval(timerInterval);
          jQuery("#entries").prop("disabled", true);

          // Auto-click the save button if textarea is not empty
          const ta = document.getElementById('entries');
          if (ta.value.trim().length > 0) {
            document.getElementById('save-btn').click();
          }
        }
    }

    function startTimer() {
      if (!timerStarted) {
        timerStarted = true;
        timerInterval = setInterval(updateTimer, 1000);
      }
    }

    // Initialize display
    updateTimer();

    // Start timer on first input
    jQuery("#entries").on("input", function () {
      startTimer();
    });
});
    // limit 45 char
    document.getElementById('entries').addEventListener('input', function (e) {
      const maxCharsPerLine = 45;
        let lines = e.target.value.split('\n');
        // Trim each line to max 45 characters
        lines = lines.map(line => line.slice(0, maxCharsPerLine));

        // Set the new trimmed value back
        e.target.value = lines.join('\n');
    });
    // End limit 45char

    /* Enable button only when text present */
    const ta = document.getElementById('entries'),
        btn = document.getElementById('save-btn');
    ta.addEventListener('input', () => btn.disabled = !ta.value.trim());

    /* Save via REST */
    btn.addEventListener('click', async () => {

        const lines = ta.value.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
        btn.disabled = true;
        btn.textContent = 'Saving…';

        const res = await fetch('<?php echo esc_url( rest_url( 'jeanius/v1/stage' ) ); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo esc_js( $rest_nonce ); ?>'
            },
            body: JSON.stringify({
                stage: 'middle_school',
                entries: lines
            })
        }).then(r => r.json());

        if (res.success) {
            // Redirect to Stage 4 (High School) – we'll add that screen next.
            location.href = '/jeanius-assessment/wizard-stage-4/';
        } else {
            //alert('Error - please try again.');
            btn.disabled = false;
            btn.textContent = 'Save & Continue';
        }
    });
    </script>

    <?php wp_footer(); ?>
</body>

</html>
<?php
}
/** ------------------------------------------------------------------
 *  Stage 4 – High School (14 – 18)
 * ------------------------------------------------------------------*/
public static function render_stage_four() {

	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) wp_die( 'Please log in first.' );

	$rest_nonce = wp_create_nonce( 'wp_rest' );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?php wp_head(); ?>
    <style>
    
    </style>
</head>

<body <?php body_class(); ?>>
    <section class="step-assessment step step-6">
    	<div class="container">
			<div class="head-logo">
    			<img src="<?php echo plugins_url( 'jeanius/public/images/logo.png' ); ?>" alt="Logo" class="logo-image img-fluid">
    		</div>
    		<div class="form-head">
			    <h3>High School</h3>
			    <h4>(15 - 18 years old)</h4>
			</div>
			<div class="content">
			    <p><span class="color-orange">:05 Minute timer begins after first answer...</span>
			    Add each milestone as a brief description on its own line. (e.g.  “1st Pet” > “Traumatic Stitches”) Do not worry about chronological order. </p>
			</div>
    		<div class="form-wrap">
                  <div class="timer-wrap">
                    <svg class="progress-ring">
                      <circle class="ring-bg" r="54" cx="75" cy="75" />
                      <circle class="ring-fill" r="54" cx="75" cy="75" />
                    </svg>
                    <div class="inner-ring">
                      <div id="timer">5</div>
                      <span>min</span>
                    </div>
                  </div>
    			<textarea id="entries" rows="8" placeholder="Bullied
Won Spelling Bee"></textarea>
            </div>
			<div class="cta-wrapper">
		    	<button id="save-btn" class="button button-primary" disabled>Continue</button>
		    </div>
		    <div class="progress">
		        <ul>
		            <li class="active">Early Childhood</li>
		            <li class="active">Elementary</li>
		            <li class="active">Middle</li>
		            <li class="active">High School</li>
		        </ul>
		    </div>
	    </div>
	</section>
    <script>
    /* countdown */
    jQuery(function () {
      const totalTime = 5 * 60; // 5 minutes in seconds
      const circle = jQuery(".ring-fill");
      const radius = circle.attr("r");
      const circumference = 2 * Math.PI * radius;
      let elapsed = 0;
      let timerStarted = false;
      let timerInterval = null;

      circle.css({
        "stroke-dasharray": circumference,
        "stroke-dashoffset": circumference // start empty
      });

      function updateTimer() {
        const remaining = totalTime - elapsed;
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;

        if (remaining > 0) {
          if (remaining >= 60) {
            jQuery("#timer").text(`${minutes}`);
            jQuery("#timer").next("span").text("min");
          } else {
            jQuery("#timer").text(`${seconds}`);
            jQuery("#timer").next("span").text("sec");
          }

          const offset = circumference - (elapsed / totalTime) * circumference;
          circle.css("stroke-dashoffset", offset);

          elapsed++; // increment at the end for next cycle
        } else {
          clearInterval(timerInterval);
          jQuery("#entries").prop("disabled", true);

          // Auto-click the save button if textarea is not empty
          const ta = document.getElementById('entries');
          if (ta.value.trim().length > 0) {
            document.getElementById('save-btn').click();
          }
        }
      }

      function startTimer() {
        if (!timerStarted) {
          timerStarted = true;
          timerInterval = setInterval(updateTimer, 1000);
        }
      }

      // Initialize display
      updateTimer();

      // Start timer on first input
      jQuery("#entries").on("input", function () {
        startTimer();
      });
    });

    //limit 45 char
    document.getElementById('entries').addEventListener('input', function (e) {
      const maxCharsPerLine = 45;
        let lines = e.target.value.split('\n');
        // Trim each line to max 45 characters
        lines = lines.map(line => line.slice(0, maxCharsPerLine));

        // Set the new trimmed value back
        e.target.value = lines.join('\n');
    });
    // End limit 45 char

    const ta = document.getElementById('entries'),
    btn = document.getElementById('save-btn');
    ta.addEventListener('input', () => btn.disabled = !ta.value.trim());

    btn.addEventListener('click', async () => {
        const lines = ta.value.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
        btn.disabled = true;
        btn.textContent = 'Saving…';

        const res = await fetch('<?php echo esc_url( rest_url( 'jeanius/v1/stage' ) ); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo esc_js( $rest_nonce ); ?>'
            },
            body: JSON.stringify({
                stage: 'high_school',
                entries: lines
            })
        }).then(r => r.json());

        if (res.success) {
            // Next screen will be the 5-minute review (to be built)
            location.href = '/jeanius-assessment/review/';
        } else {
            //alert('Error – please try again.');
            btn.disabled = false;
            btn.textContent = 'Save & Continue';
        }
    });
    </script>

    <?php wp_footer(); ?>
</body>

</html>
<?php
}

/** ------------------------------------------------------------------
 * 5-Minute Review – drag items to chronological order
 * ------------------------------------------------------------------*/
public static function render_review() {

	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) wp_die( 'Please log in.' );

	$data = json_decode( get_field( 'stage_data', $post_id ) ?: '{}', true );
	$rest_nonce = wp_create_nonce( 'wp_rest' );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?php wp_head(); ?>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <style>
    
    </style>
</head>

<body <?php body_class(); ?>>
    <section class="step-assessment step step-7">
    	<div class="container">
    		<div class="head-logo">
    			<img src="<?php echo plugins_url( 'jeanius/public/images/logo.png' ); ?>" alt="Logo" class="logo-image img-fluid">
    		</div>
    		<div class="form-head">
    			<h3>Review + Re-order<br>Your Timeline</h3>
    		</div>
    		<div class="content">
    			<p><span class="color-orange">Are all of these correct?</span><br/>Click and Drag items within each stage until they're in chronological order. You can click the ADD button to insert another word per section.</p>
    		</div>
		    <?php foreach ( $data as $stage_key => $words ) : ?>
		    <div class="stage" data-stage="<?php echo esc_attr( $stage_key ); ?>">
		        <h4><?php echo ucwords( str_replace('_',' ', $stage_key) ); ?></h4>
		        <ol>
		            <?php foreach ( $words as $w ) : ?>
		            <li contenteditable="false"><?php echo esc_html( $w ); ?></li>
		            <?php endforeach; ?>
		        </ol>
		        <button class="add-word">+ Add Word / Phrase</button>
		    </div>
		    <?php endforeach; ?>
		    <div class="cta-wrapper">
    			<button id="save" class="button button-primary">Save Order / Continue</button>
    		</div>
		</div>
	</section>
    <script>
    /* Make each UL sortable */
    document.querySelectorAll('.stage ol').forEach(el => {
        new Sortable(el, {
            animation: 150
        });
    });

    /* Add-word buttons */
    document.querySelectorAll('.add-word').forEach(btn => {
        btn.addEventListener('click', () => {
            const ul = btn.previousElementSibling;
            const li = document.createElement('li');
            li.textContent = '';
            li.contentEditable = 'true';
            ul.appendChild(li);
            li.focus();
        });
    });

    /* Save */
    document.getElementById('save').addEventListener('click', async () => {
        const ordered = {};
        document.querySelectorAll('.stage').forEach(stage => {
            const key = stage.dataset.stage;
            const words = [];
            stage.querySelectorAll('li').forEach(li => {
                const txt = li.textContent.trim();
                if (txt) words.push(txt);
            });
            ordered[key] = words;
        });
        const res = await fetch('<?php echo esc_url( rest_url( 'jeanius/v1/review' ) ); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo esc_js( $rest_nonce ); ?>'
            },
            body: JSON.stringify({
                ordered
            })
        }).then(r => r.json());

        if (res.success) {
            //alert('Order saved! (Next step will ask you to describe each word.)');
            location.href = '/jeanius-assessment/describe/';
        } else {
            //alert('Error saving – try again.');
        }
    });
    </script>

    <?php wp_footer(); ?>
</body>

</html>
<?php
}
/** --------------------------------------------------------------
 * Describe the next unfinished word
 * --------------------------------------------------------------*/
/* --------------------------------------------------------------
 * Describe screen – one word at a time
 * -------------------------------------------------------------*/
public static function render_describe() {

	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) wp_die( 'Please log in.' );

	/* -------- life-stage order ------------------------------ */
	$stage_order = [ 'early_childhood', 'elementary', 'middle_school', 'high_school' ];

	/* -------- load original words (JSON string) -------------- */
	$stage_data_raw = get_field( 'stage_data', $post_id ) ?: '{}';
	$stage_data     = json_decode( $stage_data_raw, true );

	/* -------- find first word not yet described -------------- */
	$current_stage = null;
	$current_idx   = null;
	$current_word  = null;

	foreach ( $stage_order as $stage_key ) {

		$words = $stage_data[ $stage_key ] ?? [];
		$total = count( $words );
		$done  = (int) get_post_meta( $post_id, "_{$stage_key}_done", true );

		if ( $done < $total ) {
			$current_stage = $stage_key;
			$current_idx   = $done;          // 0-based
			$current_word  = $words[ $current_idx ];
			break;
		}
	}

	/* -------- everything finished ⇒ timeline ----------------- */
	if ( $current_word === null ) {
		wp_safe_redirect( '/jeanius-assessment/timeline/' );
		exit;
	}

	/* -------- prepare variables for template ---------------- */
	$display_word = is_array( $current_word ) ? $current_word['title'] : $current_word;
	$rest_nonce   = wp_create_nonce( 'wp_rest' );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<?php wp_head(); ?>
	<style>
		/* body{font-family:sans-serif;padding:40px;max-width:700px;margin:auto}
		label{display:block;margin-top:15px}
		button{margin-top:20px} */
	.hidden { display: none; }
  #desc {
    width: 100%;
    padding: 12px;
    font-size: 15px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    line-height: 1.5;
    resize: vertical;
    box-sizing: border-box;
  }
  #word-progress {
    height: 100%;
    width: 0%;
    background-color: #999999;
    transition: width 0.3s ease-in-out;
}
	</style>
</head>
<body <?php body_class(); ?>>
<?php $total_items = array_sum( array_map( 'count', $stage_data ) ); ?>
    <section class="step-assessment step step-8">
    	<div class="container">
			<div class="head-logo">
    			<img src="<?php echo plugins_url( 'jeanius/public/images/logo.png' ); ?>" alt="Logo" class="logo-image img-fluid">
    		</div>
        <div class="progess-wrppaer">
          <div id="word-progress-container">
              <div id="word-progress"></div>
          </div>
          <div id="word-count"><span id="current_count">1</span> of <?php echo $total_items; ?></div>
        </div>
    		<div class="form-head">
    			<h3>Adding Context</h3>
    		</div>
    		<!-- PART 1 -->
			<div id="part1">
				<div class="content">
					<p class="color-orange">You Said "<?php echo esc_html( $display_word ); ?>"</p>
                    <p>Expand on this word and provide a little more detail to your answer. Keep it to 2-3 sentences.</p>
				</div>
				<textarea id="desc" rows="8" placeholder="..."></textarea>
				<div class="cta-wrapper">
					<button id="save-desc" class="button button-primary">Save + Continue</button>
				</div>
			</div>
			<!-- PART 2 -->
			<div id="part2" class="hidden part2">
				<span class="label">Was this a positive or negative experience?</span>
				<button id="pos" class="positive thumb-btn">
                    <svg id="Layer_2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 185.57 180.27">
                        <g id="Layer_1-2">
                            <path id="_2" fill="#999" fill-rule="evenodd" d="M74.38,60.68c5.92-3.38,11.03-7.81,15.15-12.97,4.19-5.25,7.34-11.26,9.29-17.7.84-2.79,1.12-6.27,1.37-9.61.13-1.66.26-3.32.52-5.34,1.21-9.4,4.29-16.46,16.92-14.84,3.02.39,5.8,1.55,8.11,3.29,2.32,1.75,4.2,4.12,5.39,6.9l.04.1c3.7,9.15,5.4,18.9,5.12,28.58-.19,6.41-1.24,12.79-3.15,18.96,7.39-.9,16.62-1.07,24.77.31,5.89.99,11.37,2.8,15.54,5.71,4.83,3.38,7.87,8.07,7.93,14.43.03,3.32-.81,7.02-2.72,11.13,1.74,1.84,3.18,3.93,4.29,6.18v.02c1.78,3.62,2.7,7.66,2.6,11.78-.09,4.17-1.2,8.18-3.13,11.7-.93,1.68-2.05,3.26-3.35,4.71,1.33,1.74,2.38,3.65,3.12,5.69,1.46,4.05,1.7,8.56.5,12.91-1.2,4.36-3.73,8.1-7.08,10.82-1.64,1.33-3.48,2.42-5.46,3.22.28,1.52.38,3.06.3,4.59-.19,3.78-1.44,7.51-3.71,10.71-3.3,4.64-8.23,6.89-13.97,7.78-5,.79-10.45.53-15.86.06h-.02c-5.45-.47-10.36-.65-15.21-.81-5.19-.18-10.36-.37-15.14-.77-19.86-1.68-29.29-7.87-37.69-13.39h0c-1.76-1.14-3.48-2.27-5.22-3.29-.7,1.43-1.62,2.72-2.73,3.83h-.02c-2.56,2.56-6.09,4.15-9.98,4.15H14.15c-3.9,0-7.44-1.6-10-4.15-2.56-2.56-4.15-6.1-4.15-10v-70.6c0-3.9,1.59-7.44,4.15-10,2.56-2.56,6.1-4.15,10-4.15h36.76c3.57,0,6.84,1.34,9.33,3.53l.38.35c2.9-3.61,6.64-8.07,12.99-13.33l.31-.26.46-.26h0ZM97.81,54.3c-4.82,6.05-10.81,11.27-17.74,15.31-5.86,4.89-9.19,9.04-11.83,12.33-1.71,2.14-3.19,3.97-4.94,5.61l-7.53,7.03-1.31-10.24c-.11-.85-.55-1.63-1.2-2.2-.62-.55-1.45-.89-2.34-.89H14.15c-.97,0-1.86.4-2.5,1.05-.64.64-1.05,1.53-1.05,2.5v70.6c0,.97.4,1.86,1.05,2.5.65.64,1.53,1.05,2.5,1.05h36.76c.98,0,1.87-.4,2.51-1.03h-.01c.65-.66,1.05-1.55,1.05-2.51v-9.18l7.14,2.66c5.09,1.9,8.96,4.44,13.05,7.12v.02c7.31,4.81,15.52,10.2,32.77,11.66,5.16.44,9.87.61,14.6.77,5.33.18,10.72.38,15.78.81,4.82.41,9.56.66,13.36.06,3.05-.47,5.56-1.5,6.97-3.48,1.08-1.52,1.67-3.3,1.76-5.08.08-1.8-.33-3.64-1.26-5.27l-4.2-7.35,8.46-.54c2.27-.14,4.38-1,6.05-2.35,1.68-1.37,2.96-3.25,3.56-5.43.61-2.19.48-4.46-.25-6.5-.74-2.04-2.1-3.86-3.97-5.15l-6.42-4.44,6.51-4.32c2.01-1.33,3.63-3.13,4.77-5.19,1.14-2.08,1.8-4.42,1.85-6.8.06-2.43-.48-4.81-1.52-6.92h0c-1.04-2.11-2.59-3.98-4.55-5.41l-3.88-2.84,2.45-4.12c2.34-3.95,3.35-7.09,3.32-9.54-.02-2.48-1.33-4.4-3.41-5.86-2.75-1.92-6.75-3.18-11.23-3.94-10.97-1.85-23.96-.57-29.8,1.34l-11.68,3.83,5.26-11.12c3.6-7.62,5.54-15.82,5.77-24.05.24-8.21-1.21-16.49-4.35-24.29-.44-1.02-1.15-1.89-2.03-2.56-.9-.68-1.96-1.13-3.06-1.27-3.69-.48-4.67,2.18-5.12,5.69-.15,1.2-.3,2.98-.44,4.8-.3,3.94-.63,8.04-1.79,11.89-2.33,7.7-6.12,14.91-11.15,21.21Z"/>
                        </g>
                    </svg>
                </button>
				<button id="neg" class="negetive thumb-btn">
                    <svg id="Layer_2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 185.57 180.27">
                        <g id="Layer_1-2">
                            <path id="_1" fill="#999" fill-rule="evenodd" d="M111.19,119.59c-5.92,3.38-11.03,7.81-15.15,12.97-4.19,5.25-7.34,11.26-9.29,17.7-.84,2.79-1.12,6.27-1.37,9.61-.13,1.66-.26,3.32-.52,5.34-1.21,9.4-4.3,16.46-16.92,14.84-3.02-.39-5.8-1.55-8.11-3.29-2.32-1.75-4.2-4.12-5.39-6.9l-.04-.1c-3.7-9.15-5.4-18.9-5.12-28.58.19-6.41,1.24-12.79,3.15-18.96-7.39.9-16.62,1.06-24.77-.31-5.89-.99-11.37-2.8-15.54-5.71-4.83-3.38-7.87-8.07-7.92-14.43-.03-3.32.81-7.02,2.72-11.13-1.74-1.84-3.18-3.93-4.29-6.18v-.02C.83,80.8-.09,76.76,0,72.63c.09-4.17,1.2-8.18,3.13-11.7.93-1.68,2.05-3.26,3.35-4.71-1.33-1.74-2.38-3.66-3.12-5.69-1.46-4.05-1.7-8.56-.5-12.92,1.2-4.35,3.73-8.1,7.08-10.82,1.64-1.33,3.48-2.42,5.46-3.22-.28-1.52-.38-3.06-.3-4.59.19-3.78,1.44-7.51,3.71-10.72,3.3-4.64,8.23-6.89,13.97-7.78,5-.78,10.45-.53,15.86-.06h.02c5.45.47,10.36.65,15.21.81,5.19.18,10.36.36,15.14.77,19.86,1.68,29.29,7.87,37.69,13.39h0c1.76,1.14,3.48,2.27,5.22,3.29.69-1.43,1.62-2.72,2.73-3.83h.02c2.56-2.56,6.09-4.15,9.98-4.15h36.76c3.9,0,7.44,1.59,10,4.15,2.56,2.56,4.15,6.1,4.15,10v70.6c0,3.9-1.59,7.44-4.15,10-2.56,2.56-6.1,4.15-10,4.15h-36.76c-3.57,0-6.84-1.34-9.33-3.53l-.38-.35c-2.91,3.61-6.64,8.07-12.99,13.33l-.31.26-.46.26h0ZM87.76,125.97c4.82-6.05,10.8-11.27,17.74-15.31,5.86-4.89,9.19-9.04,11.83-12.33,1.71-2.14,3.19-3.97,4.94-5.61l7.53-7.03,1.31,10.24c.11.85.55,1.63,1.2,2.2.62.55,1.45.89,2.34.89h36.76c.97,0,1.86-.4,2.5-1.05.64-.64,1.05-1.53,1.05-2.5V24.87c0-.97-.4-1.86-1.05-2.5-.65-.64-1.54-1.05-2.5-1.05h-36.76c-.98,0-1.87.4-2.51,1.03h.01c-.65.66-1.05,1.55-1.05,2.51v9.18l-7.14-2.66c-5.09-1.9-8.96-4.44-13.05-7.12v-.02c-7.31-4.81-15.52-10.2-32.77-11.66-5.16-.44-9.87-.61-14.6-.77-5.33-.18-10.72-.38-15.78-.81-4.82-.41-9.56-.66-13.36-.06-3.05.48-5.56,1.5-6.97,3.48-1.08,1.52-1.67,3.3-1.76,5.08-.08,1.8.33,3.64,1.26,5.27l4.2,7.35-8.46.54c-2.27.14-4.38,1-6.05,2.35-1.68,1.36-2.96,3.25-3.56,5.43-.61,2.19-.48,4.46.25,6.5.74,2.04,2.1,3.86,3.97,5.15l6.42,4.44-6.51,4.32c-2.01,1.33-3.63,3.13-4.77,5.19-1.14,2.08-1.8,4.42-1.85,6.81-.05,2.43.48,4.81,1.52,6.92h0c1.04,2.11,2.59,3.98,4.55,5.41l3.88,2.84-2.45,4.12c-2.34,3.95-3.35,7.09-3.33,9.54.02,2.48,1.34,4.4,3.41,5.86,2.75,1.92,6.75,3.18,11.23,3.93,10.97,1.85,23.96.57,29.8-1.34l11.68-3.83-5.26,11.12c-3.6,7.62-5.54,15.82-5.77,24.05-.24,8.21,1.21,16.49,4.35,24.29.44,1.02,1.15,1.89,2.03,2.56.9.68,1.95,1.13,3.06,1.27,3.69.48,4.67-2.18,5.12-5.69.15-1.2.3-2.98.44-4.8.3-3.94.63-8.04,1.79-11.89,2.33-7.7,6.12-14.91,11.15-21.21Z"/>
                        </g>
                    </svg>
                </button>

				<span class="label">How strongly did this affect you?</span>
                <div class="radios">
                    <label class="radio-option">
                        <input type="radio" name="affect" value="1" required>
                        <span class="radio-custom"></span>
                        <span class="radio-label">Just a little</span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="affect" value="2">
                        <span class="radio-custom"></span>
                        <span class="radio-label">It stirred something in me</span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="affect" value="3">
                        <span class="radio-custom"></span>
                        <span class="radio-label">I thought about it a lot</span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="affect" value="4">
                        <span class="radio-custom"></span>
                        <span class="radio-label">It deeply shaped me</span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="affect" value="5">
                        <span class="radio-custom"></span>
                        <span class="radio-label">It changed everything</span>
                    </label>
                </div>

				<div class="cta-wrapper">
					<button id="save-final" class="button button-primary">Save + Continue</button>
				</div>
			</div>
		</div>
	</section>
	<script>
    document.addEventListener("DOMContentLoaded", function() {
    	
    	// progessbar filling with steps 
  		var stepFromStorage = sessionStorage.getItem('current_step');
    	var current = parseInt(stepFromStorage, 10);
    	var total = <?php echo is_numeric($total_items) ? $total_items : 0; ?>;

    	if (!isNaN(current) && total > 0) {
        document.getElementById('current_count').textContent = current;

        // Set progress bar
        var percent = (current / total) * 100;
        document.getElementById('word-progress').style.width = percent + '%';
        } else {
	        //console.error("Invalid current or total:", current, total);
	    }
	    // End progessbar filling with steps

    	const buttons = document.querySelectorAll(".thumb-btn");

	    buttons.forEach(function(button) {
	      button.addEventListener("click", function() {
	        buttons.forEach(btn => btn.classList.remove("active"));
	        this.classList.add("active");
	      });
	    });
  });

let polarity = null;
let savedDesc = '';

document.getElementById('pos').onclick = () => polarity = 'positive';
document.getElementById('neg').onclick = () => polarity = 'negative';

document.getElementById('save-desc').addEventListener('click', () => {
	const desc = document.getElementById('desc').value.trim();
	if (!desc) {
		alert('Please enter a description.');
		return;
	}
	savedDesc = desc;

	// Hide Part 1, show Part 2
	document.getElementById('part1').classList.add('hidden');
	document.getElementById('part2').classList.remove('hidden');
});

document.getElementById('save-final').addEventListener('click', async () => {
	const btn = document.getElementById('save-final');
	const rating = jQuery('input[name="affect"]:checked').val();

	if (!polarity || !rating) {
		alert('Please complete all fields.');
		return;
	}

	btn.disabled = true;

	const res = await fetch('<?php echo esc_url(rest_url("jeanius/v1/describe")); ?>', {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': '<?php echo esc_js($rest_nonce); ?>'
		},
		body: JSON.stringify({
			stage: '<?php echo esc_js($current_stage); ?>',
			index: <?php echo $current_idx; ?>,
			title: `<?php echo esc_js($display_word); ?>`,
			description: savedDesc,
			polarity: polarity,
			rating: rating
		})
	}).then(r => r.json()).catch(() => ({ success: false }));

	if (res.success) {
		window.location.href = '/jeanius-assessment/describe/';
		let current = parseInt(jQuery('#current_count').text());
		sessionStorage.setItem('current_step', current + 1);
	} else {
		alert('Save failed — please try again.');
		btn.disabled = false;
	}
});
</script>

<?php wp_footer(); ?></body></html><?php
}


/** ------------------------------------------------------------------
 * Timeline plot – fixed-size blue dots, colored stage brackets,
 * CTA button.
 * ------------------------------------------------------------------*/
public static function render_timeline() {

	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) wp_die( 'Please log in.' );

	$raw   = \Jeanius\Rest::get_timeline_data( $post_id );
	$order = [ 'early_childhood', 'elementary', 'middle_school', 'high_school' ];
	$stageColors = [
		'early_childhood' => '#3498db',
		'elementary'      => '#2ecc71',
		'middle_school'   => '#f1c40f',
		'high_school'     => '#e74c3c',
	];
	/* keep drag order inside each stage */
	$x=0; $points=[]; $ranges=[];
	foreach($order as $stage){
		$start=$x;
		foreach(array_values(array_filter($raw,fn($r)=>$r['stage']===$stage)) as $p){
			$p['x']=$x++;                              // preserve order
			$p['y']=$p['polarity']==='negative'? -$p['rating'] : $p['rating'];
			$points[]=$p;
		}
		$end=$x-1; $x+=1.5;                          // gap
		$ranges[]=[ 'stage'=>$stage,'start'=>$start,'end'=>$end ];
	}
	$json=wp_json_encode($points); $rangesJson=wp_json_encode($ranges);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?php wp_head();?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
   .chart-container {
    max-width: 520px;
    background-color: #ffffff;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    padding: 16px;
    position: relative;
    margin: 0 auto;
  }

  #timeline {
    height: 100%;
    display: block;
  }

  #hoverLabel {
    position: absolute;
    top: 37%;
    left: 50%;
    transform: translateX(-50%);
    font-size: 14px;
    font-weight: bold;
    color: #000;
    display: none;
    z-index: 1000;
    text-align: center;
    pointer-events: none;
    background: white;
    padding: 4px 8px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 200px;
  }

  .stage-progress-wrapper {
    display: flex;
    justify-content: space-between;
    padding: 10px 0 0;
  }

  .stage-label {
    font-weight: bold;
  }

  #chartScrollWrapper {
    width: 100%;
    height: 300px;
    overflow-x: scroll;
    overflow-y: hidden;
    white-space: nowrap;
    position: relative;
    border: 4px solid #456184;
    border-radius: 12px;
    scrollbar-width: none;
    -ms-overflow-style: none;
  }

  #chartScrollWrapper::-webkit-scrollbar {
    display: none;
  }
  #genSpinner {
      font-size: 1.2rem;
      font-weight: 500;
      margin-top: 20px;
      color: #333;
      animation: fadeIn 0.5s ease-in-out;
      text-align: center;
  }

  /* Dots will just be inline span inside spinner */
  #loading-dots {
      display: inline-block;
      min-width: 1ch;
  }

  @keyframes growShake {
    0%   { transform: scale(1); }
    20%  { transform: scale(1.2); }
    40%  { transform: scale(1.1) rotate(-5deg); }
    60%  { transform: scale(1.1) rotate(5deg); }
    80%  { transform: scale(1); }
    100% { transform: scale(1); }
  }

  .button-animate {
    animation: growShake 0.6s ease;
  }

  /* Disabled button look */
  .loading-disabled {
      opacity: 0.5;
      pointer-events: none;
      background-color: #a0a0a0 !important; /* light gray or adjust as needed */
      transition: opacity 0.3s, background-color 0.3s;
  }
  </style>
</head>

<body <?php body_class();?>>
  <style>
.chart-container {
  width: 520px;
  height: 300px;
  position: relative;
  overflow: hidden;
  border: 4px solid #456184;
  border-radius: 12px;
  margin: 0 auto;
}
#timeline {
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
}
.ruler {
  position: absolute;
  top: 0;
  width: 2px;
  height: 100%;
  background: #000;
  z-index: 10;
}
.thumb-container {
  	width: 520px;
    margin: 0 auto;
    position: relative;
    height: 20px;
    background-color: #e6e6e6;
    margin-top: 12px;
    border-radius: 25px;
    cursor: pointer;
}
.thumb {
  position: absolute;
  top: 0;
  width: 20px;
  height: 20px;
  background-color: #456184;
  border-radius: 50%;
  transform: translateX(-30%);
  z-index: 11;
  cursor: pointer;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}
#hoverLabel {
  position: absolute;
  top: 37%;
  left: 50%;
  transform: translateX(-50%);
  font-size: 14px;
  font-weight: bold;
  color: #000;
  display: none;
  z-index: 1000;
  text-align: center;
  pointer-events: none;
  background: white;
  padding: 4px 8px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.15);
  white-space: nowrap;
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
}
#stageDynamicLabels {
  position: absolute;
  bottom: 8px;
  left: 0;
  height: 24px;
  pointer-events: none;
  overflow: hidden;
  white-space: nowrap;
  width: 3000px;
}

</style>

<section class="step-assessment step step-9">
  <div class="container">
    <div class="head-logo">
      <img src="<?php echo plugins_url( 'jeanius/public/images/logo.png' ); ?>" alt="Logo" class="logo-image img-fluid">
    </div>
    <div class="form-head">
      <h3 id="report-title">Your Blueprint is Loading</h3>
    </div>
    <div class="content">
      <h4 class="color-orange">Here's Your Life Time Timeline!</h4>
      <p>Scroll sideways to explore each milestone.</p>
    </div>

    <div class="chart-container" id="chartWrapper">
	  <canvas id="timeline" width="3000" height="300"></canvas>
	  <div id="stageDynamicLabels"></div>
	  <div class="ruler" id="rulerLine"></div>
	  <div id="hoverLabel"></div>
	</div>

	<div class="thumb-container">
	  <div class="thumb" id="thumb"></div>
	</div>



    <div class="cta-wrapper">
      <button id="result-btn" class="button button-primary cta color-blue-dark" onclick="location.href='/jeanius-assessment/results/'">
        See Full Blueprint
      </button>
      <button class="button button-primary cta" onclick="#">
        Schedule Follow-up
      </button>
    </div>
  </div>
</section>


    <script>
	// destry session variable for count words
	document.addEventListener('DOMContentLoaded', function () {
	    var currentStep = sessionStorage.getItem('current_step');

	    if (currentStep) {
	        sessionStorage.removeItem('current_step');
	    }
	});
	// End destry session variable for count words

    (() => {
        const result_btn = document.getElementById('result-btn');
        const reportTitle = document.getElementById('report-title');
        // Disable the button and style it as inactive
        result_btn.disabled = true;
        result_btn.classList.add('loading-disabled');

        // Set initial title with dots container
        const dots = document.createElement('span');
        dots.id = 'loading-dots';
        reportTitle.textContent = 'Your Blueprint is Loading';
        reportTitle.appendChild(dots);

        // Animate the dots
        let dotCount = 0;
        const dotInterval = setInterval(() => {
            dotCount = (dotCount + 1) % 4;
            dots.textContent = '.'.repeat(dotCount);
        }, 500);

        fetch('<?php echo esc_url( rest_url( 'jeanius/v1/generate' ) ); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( "wp_rest" ) ); ?>'
            }
        }).then(r => r.json()).then(() => {
            clearInterval(dotInterval); // stop dots animation
            //spinner.remove(); // remove loading text
            reportTitle.textContent = 'See your Blueprint Below';
            result_btn.disabled = false;
            result_btn.classList.remove('loading-disabled');
            result_btn.classList.add('button-animate'); // Trigger the one-time animation to button
            setTimeout(() => {
                result_btn.classList.remove('button-animate');
            }, 600);
        }).catch(() => {
            clearInterval(dotInterval);
            spinner.textContent = '⚠️ Report generation failed. You can still view the timeline.';
            result_btn.disabled = true;
        });
    })();
</script>


<!-- Chart.js & Annotation Plugin -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.4.0"></script>

<script>

const pts = <?php echo $json;?>;
console.log(pts);

const stageMap = {};
pts.forEach(p => {
  if (!stageMap[p.stage]) stageMap[p.stage] = [];
  stageMap[p.stage].push(p);
});

const stageList = Object.entries(stageMap).map(([key, val]) => ({
  stage: key,
  order: val[0].stage_order
})).sort((a, b) => a.order - b.order);

const orderedStages = stageList.map(s => s.stage);
const xOffsetPerStage = 7;
const canvas = document.getElementById('timeline');
canvas.width = orderedStages.length * 260;

const mergedData = [];
orderedStages.forEach((stage, index) => {
  const stageData = stageMap[stage] || [];
  const offset = index * xOffsetPerStage;
  const count = stageData.length;

  stageData.forEach((point, i) => {
    const xInBlock = (i + 1) * (xOffsetPerStage / (count + 1));
    mergedData.push({
      ...point,
      x: offset + xInBlock
    });
  });
});

const minX = 0;
const maxX = orderedStages.length * xOffsetPerStage;

const blueStar = new Image(30, 30);
blueStar.src = 'https://jeaniusdev.wpenginepowered.com/wp-content/themes/jeanius/assets/images/star-blue.svg';
const orangeStar = new Image(30, 30);
orangeStar.src = 'https://jeaniusdev.wpenginepowered.com/wp-content/themes/jeanius/assets/images/star-orange.svg';

const ctx = document.getElementById('timeline').getContext('2d');
const chart = new Chart(ctx, {
  type: 'scatter',
  data: {
    datasets: [{
      label: '',
      data: mergedData,
      backgroundColor: ctx => ctx.raw.rating === 5 ? undefined : (ctx.raw.y >= 0 ? '#2980b9' : '#ed5b26'),
      radius: ctx => ctx.raw.rating === 5 ? 16 : 5 + (ctx.raw.rating - 1) * 2,
      pointStyle: ctx => {
        if (ctx.raw.rating === 5) {
          return ctx.raw.y >= 0 ? blueStar : orangeStar;
        }
        return 'circle';
      },
      hoverRadius: ctx => ctx.raw.rating === 5 ? 16 : 5 + (ctx.raw.rating - 1) * 2,
      hoverBorderWidth: 0,
      borderWidth: 0
    }]
  },
  options: {
    responsive: false,
    plugins: {
      tooltip: {
        enabled: false,
        external: function (context) {
          const tooltipModel = context.tooltip;
          const hoverLabel = document.getElementById('hoverLabel');
          if (!tooltipModel || !tooltipModel.dataPoints || !tooltipModel.dataPoints.length) {
            hoverLabel.style.display = 'none';
            return;
          }
          const dp = tooltipModel.dataPoints[0];
          hoverLabel.innerText = dp.raw.label;
          hoverLabel.style.display = 'block';
          hoverLabel.style.color = dp.raw.y >= 0 ? '#2980b9' : '#ed5b26';
        }
      },
      annotation: {
        annotations: {
          bottomZone: {
            type: 'box',
            yMin: -7,
            yMax: 0,
            backgroundColor: 'rgba(242, 91, 36, 0.15)',
            borderColor: '#ed5b26',
            borderWidth: 2
          },
          topZone: {
            type: 'box',
            yMin: 0,
            yMax: 7,
            backgroundColor: 'rgba(69, 98, 133, 0.15)',
            borderColor: '#456285',
            borderWidth: 2
          }
        }
      },
      interaction: {
        mode: 'nearest',
        intersect: true
      },
      animation: false,
      legend: { display: false }
    },
    scales: {
      x: {
        type: 'linear',
        min: minX,
        max: maxX,
        ticks: { stepSize: 1, display: false },
        grid: { color: '#ddd', drawTicks: false }
      },
      y: {
        min: -7,
        max: 7,
        ticks: { stepSize: 2, display: false },
        grid: { color: '#ddd', drawTicks: false }
      }
    }
  }, 
  plugins: []
});

// Render stage labels
function renderMovingStageLabels() {
  const labelContainer = document.getElementById('stageDynamicLabels');
  labelContainer.innerHTML = '';
  const stageWidth = canvas.width / orderedStages.length;

  orderedStages.forEach((stage, index) => {
    const label = document.createElement('div');
    label.textContent = stage.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    label.className = 'stage-label';
    label.style.position = 'absolute';
    label.style.left = `${index * stageWidth + 15}px`;
    label.style.bottom = '4px';
    label.style.whiteSpace = 'nowrap';
    label.style.fontSize = '18px';
    label.style.color = '#3d4b56';
    label.style.pointerEvents = 'none';
    labelContainer.appendChild(label);
  });
}
renderMovingStageLabels();

const chartWrapper = document.getElementById('chartWrapper');
const ruler = document.getElementById('rulerLine');
const thumb = document.getElementById('thumb');
const viewWidth = chartWrapper.clientWidth;
const chartWidth = canvas.width;
const maxOffset = chartWidth - viewWidth;
const dataset = chart.data.datasets[0].data;
const xMin = chart.options.scales.x.min;
const xMax = chart.options.scales.x.max;

function setRulerPosition(x) {
  const boundedX = Math.max(0, Math.min(x, viewWidth));
  ruler.style.left = `${boundedX}px`;
  thumb.style.left = `${boundedX}px`;

  const scrollRatio = boundedX / viewWidth;
  const scrollOffset = scrollRatio * maxOffset;
  canvas.style.left = `-${scrollOffset}px`;
  document.getElementById('stageDynamicLabels').style.left = `-${scrollOffset}px`;

  const chartX = xMin + ((xMax - xMin) * (scrollOffset + boundedX) / chartWidth);

  const closestPoint = dataset.reduce((prev, curr) =>
    Math.abs(curr.x - chartX) < Math.abs(prev.x - chartX) ? curr : prev
  );

  const scale = chart.scales.x;
  const dotPixel = scale.getPixelForValue(closestPoint.x);
  const rulerPixel = scrollOffset + boundedX;
  const dotRadius = 5 + closestPoint.rating;
  const distance = Math.abs(dotPixel - rulerPixel);

  const hoverLabel = document.getElementById('hoverLabel');
  if (distance <= dotRadius) {
    hoverLabel.textContent = `${closestPoint.label}`;
    hoverLabel.style.display = 'block';
    hoverLabel.style.backgroundColor = '#fff';
    hoverLabel.style.color = closestPoint.polarity === 'positive' ? '#2980b9' : '#f15a24';
  } else {
    hoverLabel.style.display = 'none';
  }
}

let isDragging = false;

thumb.addEventListener('mousedown', () => {
  isDragging = true;
});

document.addEventListener('mousemove', (e) => {
  if (!isDragging) return;
  const bounds = chartWrapper.getBoundingClientRect();
  const localX = e.clientX - bounds.left;
  setRulerPosition(localX);
});

document.addEventListener('mouseup', () => {
  isDragging = false;
});

setRulerPosition(0); // Initial ruler position
  </script>

 <?php wp_footer();?>
</body>

</html><?php
}


/** ------------------------------------------------------------------
 * Results screen – prints pre-formatted HTML from ACF “_md_copy” fields.
 * If those fields are still empty it triggers /generate once, then reloads.
 * ------------------------------------------------------------------*/
public static function render_results() {

	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) wp_die( 'Please log in.' );

	/* ── Grab HTML blocks ────────────────────────────────────────── */
	$sections = [
		'Ownership Stakes'     => get_field( 'ownership_stakes_md_copy',     $post_id ),
		'Life Messages'        => get_field( 'life_messages_md_copy',        $post_id ),
		'Transcendent Threads' => get_field( 'transcendent_threads_md_copy', $post_id ),
		'Sum of Your Jeanius'  => get_field( 'sum_jeanius_md_copy',          $post_id ),
		'College Essay Topics' => get_field( 'essay_topics_md_copy',         $post_id ),
	];

	$is_ready   = array_filter( $sections ) !== [];      // any HTML present?
	$rest_nonce = wp_create_nonce( 'wp_rest' );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
    <?php wp_head(); ?>
    <style>
    body {
        font-family: Georgia, serif;
        padding: 40px;
        max-width: 860px;
        margin: auto
    }

    h2 {
        color: #003d7c;
        margin-top: 2.2rem;
        border-bottom: 1px solid #eee;
        padding-bottom: 4px
    }

    #loading {
        font-size: 1.1rem
    }

    table {
        border-collapse: collapse;
        width: 100%;
        margin-bottom: 1em
    }

    th,
    td {
        border: 1px solid #ddd;
        padding: 6px
    }

    ul {
        margin-left: 1.2em
    }
    </style>
</head>

<body <?php body_class(); ?>>

    <h1>Your Jeanius Insights Report</h1>

    <div id="loading" <?php if ( $is_ready ) echo ' style="display:none"'; ?>>
        Generating your report… this may take up to a minute.
    </div>

    <div id="report">
        <?php
	    if ( $is_ready ) {
	        foreach ( $sections as $title => $html ) {
	            if ( ! $html ) continue;
	            $anchor_link = preg_replace("/[^A-Za-z0-9]/", '-', strtolower(((isset($title) && $title) ? $title : '')));

	            echo '<section id="'.$anchor_link.'">';
	            echo '<h2>'. esc_html( $title ) .'</h2>';
	            echo wp_kses_post( $html );   // already HTML
	            echo '</section>';
	        } ?>
	        <div class="cta-wrapper report-pdf">
	        	<a id="sendPdfBtn" href="javascript:void(0);" class="button">Send the blueprint results as a PDF to yourself/or someone else</a>
	        </div>
	    <?php }
	    ?>
    </div>
    <script type="text/javascript">
    	const $p = jQuery('#ownership-stakes p');
		// Remove the text node "Ownership Stakes:"
		$p.contents().filter(function() {
		  return this.nodeType === 3 && this.nodeValue.trim().startsWith('Ownership Stakes:');
		}).each(function() {
		  jQuery(this).remove();
		});

		// Remove the <br> immediately after the removed text node
		$p.contents().filter(function() {
		  return this.nodeType === 1 && this.tagName === 'BR';
		}).first().remove();

		// If the first child is now a <br>, remove it as well
		if ($p.contents().first().is('br')) {
		  $p.contents().first().remove();
		}

    // generating PDF to send
    document.addEventListener("DOMContentLoaded", function () {
      const button = document.getElementById("sendPdfBtn");

      if (button) {
        button.addEventListener("click", function () {
          // Clone page HTML and remove the button
          const cloned = document.body.cloneNode(true);
          const btn = cloned.querySelector("#sendPdfBtn");
          if (btn) btn.remove();

          const html = "<!DOCTYPE html>" + cloned.outerHTML;
          fetch("/wp-admin/admin-ajax.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: new URLSearchParams({
              action: "send_results_pdf_from_dom",
              html: html,
            }),
          })
            .then((res) => res.text())
            .then((response) => {
              alert("PDF has been emailed!");
            });
        });
      }
    });
    // End generating PDF to send

    </script>
    <?php if ( ! $is_ready ) : ?>
    <script>
    fetch('<?php echo esc_url( rest_url( 'jeanius/v1/generate' ) ); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': '<?php echo esc_js( $rest_nonce ); ?>'
            }
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'ready') {
                location.reload(); // re-load to pull the new HTML fields
            } else {
                document.getElementById('loading').textContent =
                    '⚠️ Error generating report — please reload.';
            }
        })
        .catch(() => {
            document.getElementById('loading').textContent =
                '⚠️ Network error — please reload.';
        });
    </script>
    <?php endif; ?>

    <?php wp_footer(); ?>
</body>

</html><?php
}
}