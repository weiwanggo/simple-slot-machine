jQuery(document).ready(function ($) {
    let intervals = [];
    let isSpinning = false;
    let shootingInterval;
    let spinTimeout;
    let isAudioLoaded = false;

    const baseUrl = '/wp-content/plugins/simple-slot-machine/';
    const baseImageUrl = baseUrl + 'assets/images/';
    const symbols = [
        baseUrl + 'assets/images/image1.png',
        baseUrl + 'assets/images/image2.png',
        baseUrl + 'assets/images/image3.png',
        baseUrl + 'assets/images/image4.png',
        baseUrl + 'assets/images/image5.png',
        baseUrl + 'assets/images/image6.png'
    ];

    const bonus = [
        baseUrl + 'assets/images/rmb.png',
        baseUrl + 'assets/images/yuanbao.png',
        baseUrl + 'assets/images/goldcoin.png',
        baseUrl + 'assets/images/goldbar.png'
    ];

    // Create audio elements
    const spinAudio = new Audio(baseUrl + 'assets/audio/spin.mp3');
    //spinAudio.loop = true;
    const winAudio = new Audio(baseUrl + 'assets/audio/win.mp3');
    const jackportAudio = new Audio(baseUrl + 'assets/audio/jackport.mp3');
    const loseAudio = new Audio(baseUrl + 'assets/audio/lose.mp3');
    const delays = [0, 1000, 4000]; 


    let audio = null;

    function resetAudio() {
        if (audio != null && !audio.paused) {
            audio.pause();
            audio.currentTime = 0;
        }
        //$('#jackpot-animation').css('display', 'none');
    }

    // Initialize reels with default symbols
    $('#reel1').html('<img src="' + symbols[0] + '" alt="Reel 1">');
    $('#reel2').html('<img src="' + symbols[0] + '" alt="Reel 2">');
    $('#reel3').html('<img src="' + symbols[0] + '" alt="Reel 3">');

    $('#toggleButton').on('click', function () {
        if (!isAudioLoaded) {
            winAudio.load();
            loseAudio.load();
            jackportAudio.load();
            isAudioLoaded = true;
        }
        toggleSpin();
    });

    $("#dismissButton").on("click", function () {
        $("#resultModal").css({
            border: "",
            background: "",
        });
        $("#resultModal").fadeOut();
        $("#toggleButton").prop("disabled", false); // Re-enable the button
        $("#bet").prop("disabled", false); // Re-enable the button
    });

    // Function to toggle spinning state
    function toggleSpin() {
        if (isSpinning) {
            stopSpin(); // Stop spinning if currently spinning
        } else {
            startSpin(); // Start spinning if not already spinning
        }
    }

    function startSpin() {
        resetAudio();
        clearInterval(shootingInterval);
        $('#toggleButton').prop('disabled', true);
        $('#bet').prop('disabled', true);
        $('#jackpot-animation').empty();
        $('#result').removeClass('jackpot-message');
        const bet = $('#bet').val();
        $.post(
            ajaxData.ajaxUrl,
            { action: 'start_spin', bet: bet },
            function (response) {
                if (response.success) {
                    isSpinning = true;
                    $('#result').text('Spinning......');
                    $('#balance').html('Your balance: ' + response.data.balance);
                    $('#remaining-plays').html("Today's Play Count: " + response.data.playCount);
                    ['#reel1 img', '#reel2 img', '#reel3 img'].forEach((selector, index) => {
                        audio = spinAudio;
                        audio.play();
                        if (intervals[index]) {
                            clearInterval(intervals[index]);
                        }
                        intervals[index] = setInterval(() => {
                            const randomSymbol = symbols[Math.floor(Math.random() * symbols.length)];
                            $(selector).attr('src', randomSymbol);
                        }, 80); // Change image every 100ms
                        spinTimeout = setTimeout(stopSpin, 12000);
                        //audio.addEventListener("ended", stopSpin);
                    });
                } else {
                    showMessage(response.data.message || 'An error occurred.', '😞');
                    
                }
            }
        ).fail(() => {
            showMessage('Failed to process your request. Please try again.', '😞');
            isSpinning = false;
        });
    }

    function stopSpin() {
        if (isSpinning) {
            isSpinning = false;
            clearTimeout(spinTimeout);
            const bet = $('#bet').val();

            // AJAX request to calculate results
            $.post(
                ajaxData.ajaxUrl,
                { action: 'slot_machine_spin', bet: bet },
                function (response) {
                    if (response.success) {
                        const reels = response.data.reels;
                        //intervals.forEach(interval => clearInterval(interval));
                        reels.forEach((result, index) => {
                            setTimeout(() => {
                                clearInterval(intervals[index]);
                                $(`#reel${index + 1} img`).attr('src', `${baseImageUrl}${result}.png`);
                            }, delays[index]);
                        });
                        resetAudio();

                        // Update the reels with the result
                        
                        $('#balance').html('Your balance: ' + response.data.balance);

                        setTimeout(() => showResult(response), 5000);

                    } else {
                        showMessage(response.data.message || 'An error occurred.', '😞');
                    }
                }
            ).fail(() => {
                showMessage('Failed to process your request. Please try again.', '😞');
            });
        }
    }

    function showResult(response) {
        if (response.data.winnings > 0) {
            if (response.data.result == '100x') {
                audio = jackportAudio;
                audio.play();
                shootingInterval = setInterval(shootIcon, 100); // Add a new icon every 100ms
                $('#result').addClass('jackpot-message');
                $('#toggleButton').prop('disabled', true)
                $('#bet').prop('disabled', true)

                audio.addEventListener("ended", function () {
                    clearInterval(shootingInterval);
                    $('#jackpot-animation').empty();
                    $('#result').removeClass('jackpot-message');
                    $("#modalMessage").html('<strong>JACKPOT!!! 🎉</strong> You hit the big win!');
                    $("#modalFace").html("🥳");
                    $("#resultModal").css({
                        border: "4px solid gold",
                        background: "linear-gradient(45deg, #ffdd57, #ffb347)"
                    }).fadeIn();

                    //$('#toggleButton').prop('disabled', false)
                });

            }
            else {
                audio = winAudio;
                audio.play();
                showMessage('You won ' + response.data.winnings + ' points!', "😊");
            }
            if (response.data.animation != "") {
                const [repeatNum, animationName] = response.data.animation.split("x");
                const repeatCount = parseInt(repeatNum, 10);
                const animationFunction = window[animationName];

                // Assuming animations are global functions

                if (typeof animationFunction === "function") {
                    for (let i = 0; i < repeatCount; i++) {
                        animationFunction(); // Call the function N times
                    }
                }
            }

            $('#result').text('You won ' + response.data.winnings + ' points!');

        }
        else {
            audio = loseAudio;
            audio.play();
            showMessage('You lost. Try again!', "😞");
        }
    }

    const shootIcon = () => {
        // Create the icon element
        const icon = document.createElement('div');
        icon.className = 'icon';

        // Get a random symbol from the array
        const index = Math.floor(Math.random() * bonus.length);
        console.log(index); // Log the index
        console.log(bonus[index]); // Log the chosen symbol

        // Set the background image and styles
        if (bonus[index]) {
            $(icon).css('background-image', `url("${bonus[index]}")`);
        } else {
            console.error(`Invalid symbol at index ${index}`);
        }

        $(icon).css({
            left: `${Math.random() * 90}vw`, // Random horizontal position
            'animation-duration': `${Math.random() * 0.5 + 1}s` // Random duration
        });

        // Append the icon to the animation container
        $('#jackpot-animation').append(icon);

        // Remove the icon after the animation ends
        setTimeout(() => {
            $(icon).remove(); // Properly remove the specific icon
        }, 1500); // Match animation duration
    };

    function showMessage(message, icon){
        $('#result').text(message);
        $("#modalMessage").text(message);
        $("#modalFace").text(icon);
        $("#resultModal").fadeIn();
    }
});
