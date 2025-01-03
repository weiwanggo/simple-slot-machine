<?php
/**
 * Plugin Name: Simple Slot Machine
 * Description: A simple slot machine to use/earn myCred points.
 * Version: 1.0
 * Author: Your Name
 */
const OUTCOMES = [
    ["result" => "no_win", "probability" => 59],
    ["result" => "2x", "probability" => 33],
    ["result" => "5x", "probability" => 7],
    ["result" => "20x", "probability" => 0.9],
    ["result" => "100x", "probability" => 0.1]
];

const HIGH_WIN_OUTCOMES = [
    ["result" => "no_win", "probability" => 59],
    ["result" => "2x", "probability" => 33],
    ["result" => "5x", "probability" => 7],
    ["result" => "20x", "probability" => 0.9],
    ["result" => "100x", "probability" => 0.1]
];

// Define the images and their corresponding multipliers
const IMAGES = [
    "image1" => [100, "20xbeeAnimation"],
    "image2" => [20, "1xblueangelAnimation"],
    "image3" => [20, "10xfireflyAnimation"],
    "image4" => [5, "1xgardeniafairyAnimation"],
    "image5" => [5, "1xcopterAnimation"],
    "image6" => [5, "1xhibiscusangelAnimation"]
];

const MYCRED_REF_BET = 'Slot Machine Bet';
const MYCRED_REF_RESULT = 'Slot Machine Result';
const DAILY_LIMIT = 100;

const BETS = [1, 2, 5, 10];

// Enqueue assets
function slot_machine_enqueue_scripts()
{
    wp_enqueue_script('slot-machine-js', plugins_url('assets/slot-machine.js', __FILE__), array('jquery'), '1.0', true);
    wp_enqueue_style('slot-machine-css', plugins_url('assets/slot-machine.css', __FILE__));
    wp_localize_script('slot-machine-js', 'ajaxData', array(
        'ajaxUrl' => admin_url('admin-ajax.php')
    ));

}
add_action('wp_enqueue_scripts', 'slot_machine_enqueue_scripts');

// Shortcode for slot machine
function slot_machine_shortcode()
{
    if (!is_user_logged_in()) {
        $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $login_url = wp_login_url($current_url);

        return '<div id="slot-machine"><a href="' . $login_url . '"><h6 class="text">Please log in to play</h6></a>';
    }
    $user_id = get_current_user_id();
    $play_count = get_daily_slot_bets($user_id);
    $balance = mycred_get_users_balance($user_id);

    ob_start(); ?>
    <div id="slot-machine-container">
        <div id="slot-machine">
            <h2 class="text">幸运转盘 <br />Lucky Spin</h2>
            <div id="reels">
                <div class="reel" id="reel1"></div>
                <div class="reel" id="reel2"></div>
                <div class="reel" id="reel3"></div>
            </div>
            <div id="controls">
                <label for="bet" class="text">Bet:</label>
                <select id="bet">
                    <option value="1">1 Point</option>
                    <option value="2">2 Points</option>
                    <option value="5">5 Points</option>
                    <option value="10">10 Points</option>
                </select>
                <button id="toggleButton">Spin</button>

            </div>
            <div class="slot-info">
                <div class="balance text">Balance: <span id="balance"><?php echo $balance ?></span></div>
                <div class="award text">Today's Play Count: <span id="play-count"><?php echo $play_count . '/' . DAILY_LIMIT; ?></span></div>
                <div class="status text" id="result">Click Spin button to play. Good Luck!</div>
            </div>
            <div id="resultModal" class="modal">
                <div class="modal-result">
                    <span id="modalFace" class="face"></span>
                    <p id="modalMessage"></p>
                    <button id="dismissButton" class="dismiss-button">Continue</button>
                </div>
            </div>
        </div>
        <div id="jackpot-animation"></div>
    </div>
    <script type="module" src="/wp-content/themes/glytch-child/js/surprise-list.js"></script>

    <?php
    return ob_get_clean();
}
add_shortcode('slot-machine', 'slot_machine_shortcode');

function isLuckyRole($user)
{
    if (in_array('administrator', $user->roles) || in_array('captain', $user->roles)) {
        return true;
    }
    return false;
}

function slot_machine_spin()
{
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'You must be logged in to play.']);
    }

    $bet = intval($_POST['bet']);
    if (!in_array($bet, BETS)) {
        wp_send_json_error(['message' => 'Invalid bet ' . $bet]);
    }

    $user = wp_get_current_user();
    $user_id = $user->ID;
    $isHighWin = false;
    if (in_array('administrator', $user->roles) || in_array('captain', $user->roles)) {
        $isHighWin = true;
    }

    $winningResult = $isHighWin ? weighted_random_choice(HIGH_WIN_OUTCOMES) : weighted_random_choice(OUTCOMES);
    $reels = arrange_reels($winningResult, IMAGES);
    $animation = '';
    // Check if all elements are the same
    if (count(array_unique($reels)) === 1) {
        $animation = IMAGES[$reels[0]][1];
    }

    $winnings = $winningResult !== "no_win" ? $bet * intval($winningResult) : 0;

    // Check if user wins
    if ($winnings > 0) {
        // Add winnings to user's points
        mycred_add(MYCRED_REF_RESULT, $user_id, $winnings, 'Slot machine win');
    }
    $balance = mycred_get_users_balance($user_id);

    wp_send_json_success(['reels' => $reels, 'winnings' => $winnings, 'result' => $winningResult, 'balance' => $balance, 'animation' => $animation]);
}

// Function to select a weighted random outcome
function weighted_random_choice($outcomes)
{
    $totalWeight = array_sum(array_column($outcomes, 'probability'));
    $randVal = mt_rand(0, $totalWeight * 100) / 100; // Generate random number in [0, totalWeight] 
    $cumulativeWeight = 0;

    foreach ($outcomes as $outcome) {
        $cumulativeWeight += $outcome["probability"];
        if ($randVal <= $cumulativeWeight) {
            return $outcome["result"];
        }
    }
    return "no_win"; // Fallback, though it should never occur
}

// Function to arrange reels based on the result
function arrange_reels($result, $images)
{
    if ($result == "no_win") {
        // Randomly select three different images
        $keys = array_keys($images);
        shuffle($keys);
        return array_slice($keys, 0, 3);
    } elseif ($result == "2x") {
        // Select one matching image and one different image
        $keys = array_keys($images);
        $matchingImage = $keys[array_rand($keys)];
        $otherImage = $keys[array_rand(array_diff($keys, [$matchingImage]))];
        $reels = [$matchingImage, $matchingImage, $otherImage];
        shuffle($reels);
        return $reels;
    } else {
        // Extract multiplier from result (e.g., "5x" -> 5)
        $winningMultiplier = intval($result);
        $winningImages = array_keys(array_filter($images, function ($value) use ($winningMultiplier) {
            return $value[0] == $winningMultiplier;
        }));
        $winningImage = $winningImages[array_rand($winningImages)];
        return [$winningImage, $winningImage, $winningImage];
    }
}


function start_spin()
{
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'You must be logged in to play.']);
    }

    if (!function_exists('mycred')) {
        wp_send_json_error(['message' => 'myCred plugin is not active.']);
    }

    $bet = intval($_POST['bet']);
    if (!in_array($bet, BETS)) {
        wp_send_json_error(['message' => 'Invalid bet ' . $bet]);
    }

    $user = wp_get_current_user();
    $user_id = $user->ID;

    $playCount = get_daily_slot_bets($user_id);

    if ($playCount >= DAILY_LIMIT) {
        wp_send_json_error(['message' => 'You have reached your daily play limit,  please come back tomorrow!']);
    }

    // only admin and captain can play
    if (!in_array('administrator', $user->roles) && !in_array('captain', $user->roles)) {
        wp_send_json_error(['message' => 'You are not allowed to play. Contact Administrator for help.']);
    }


    // Get user's current balance
    $balance = mycred_get_users_balance($user_id);
    $playCount++;

    // Check if user has enough points
    if ($bet <= 0 || $balance < $bet) {
        wp_send_json_error(['message' => 'Insufficient points.']);
    }

    // Subtract the bet from the user's balance
    mycred_subtract(MYCRED_REF_BET, $user_id, $bet, 'Slot machine bet');
    $balance = mycred_get_users_balance($user_id);

    wp_send_json_success(['balance' => $balance, 'playCount' => $playCount . '/' . DAILY_LIMIT]);
}

function get_daily_slot_bets($user_id)
{
    global $wpdb;

    // Set China timezone
    $timezone = new DateTimeZone('Asia/Shanghai');
    $start_of_day = new DateTime('today', $timezone);
    $end_of_day = new DateTime('tomorrow', $timezone);
    $end_of_day->modify('-1 second'); // End of the day (23:59:59)

    // Convert to UNIX timestamp
    $start_timestamp = $start_of_day->getTimestamp();
    $end_timestamp = $end_of_day->getTimestamp();

    $query = $wpdb->prepare(
        "
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}myCRED_log 
        WHERE user_id = %d 
        AND ref = %s 
        AND time BETWEEN %d AND %d
    ",
        $user_id,
        MYCRED_REF_BET, // Target only 'bet' entries
        $start_timestamp,
        $end_timestamp
    );

    return (int) $wpdb->get_var($query);
}



add_action('wp_ajax_slot_machine_spin', 'slot_machine_spin');
add_action('wp_ajax_start_spin', 'start_spin');
?>