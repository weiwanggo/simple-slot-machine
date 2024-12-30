<?php
/**
 * Plugin Name: Simple Slot Machine
 * Description: A simple slot machine to use/earn myCred points.
 * Version: 1.0
 * Author: Your Name
 */
const OUTCOMES = [
    ["result" => "no_win", "probability" => 49],
    ["result" => "2x", "probability" => 30],
    ["result" => "5x", "probability" => 25],
    ["result" => "20x", "probability" => 10],
    ["result" => "100x", "probability" => 5]
];

const HIGH_WIN_OUTCOMES = [
    ["result" => "no_win", "probability" => 20],
    ["result" => "2x", "probability" => 20],
    ["result" => "5x", "probability" => 20],
    ["result" => "20x", "probability" => 20],
    ["result" => "100x", "probability" => 20]
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
        return '<div id="slot-machine"><h2 class="text">Please log in to play. 请登录。</h2>';
    }
    $user_id = get_current_user_id();
    $balance = mycred_get_users_balance($user_id);

    ob_start(); ?>
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
            <div id="balance" class="text">Balance: <?php echo $balance ?></div>
        </div>
        <p id="result" class="text"></p>
        <div id="resultModal" class="modal">
        <div class="modal-result">
            <span id="modalFace" class="face"></span>
            <p id="modalMessage"></p>
            <button id="dismissButton" class="dismiss-button">Continue</button>
        </div>
    </div>
    </div>
    <div id="jackpot-animation"></div>
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
        mycred_add('Slot Machine', $user_id, $winnings, 'Slot machine win');
    }
    $balance = mycred_get_users_balance($user_id);

    wp_send_json_success(['reels' => $reels, 'winnings' => $winnings, 'result' => $winningResult, 'balance' => $balance, 'animation' => $animation]);
}

// Function to select a weighted random outcome
function weighted_random_choice($outcomes)
{
    $totalWeight = array_sum(array_column($outcomes, 'probability'));
    $randVal = mt_rand(0, $totalWeight * 100) / 100; // Generate random number in [0, totalWeight]
    // Uncomment below for testing
    //   $randVal =100;     
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
    $user_id = get_current_user_id();

    // Get user's current balance
    $balance = mycred_get_users_balance($user_id);

    // Check if user has enough points
    if ($bet <= 0 || $balance < $bet) {
        wp_send_json_error(['message' => 'Insufficient points.']);
    }

    // Subtract the bet from the user's balance
    mycred_subtract('Slot Machine', $user_id, $bet, 'Slot machine bet');
    $balance = mycred_get_users_balance($user_id);

    wp_send_json_success(['balance' => $balance]);
}


add_action('wp_ajax_slot_machine_spin', 'slot_machine_spin');
add_action('wp_ajax_start_spin', 'start_spin');
?>
