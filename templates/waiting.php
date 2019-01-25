<?php require 'header.php' ?>

<div class="jumbotron">
    <div class="container">
        <h1>Raffle started</h1>
        <h2>Send a text to <?= $phoneNumber ?> with code <?= $code ?></h2>
        <h3>Current entrants: <span id="entrant_count"><?= $entrantCount ?></span></h3>
        <span id="is-done"></span>
        <?php if (isset($entrantNumbers)): ?>
            <ul id="entrant_numbers">
            <?php foreach ($entrantNumbers as $number): ?>
                <li><?= $maskNumber($number) ?></li>
            <?php endforeach ?>
            </ul>
            <form method="post">
                <p><input type="submit" class="btn btn-primary btn-lg" role="button" value="Select Winners" /></p>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php $script = "<script>var interval; $(
    interval = setInterval(function() {
        $.getJSON('?show=entrants', {}, function(data) {
            if (data.is_complete === true) {
                document.getElementById('is-done').innerHTML = 'Raffle has been closed. Please refresh the page.';
                clearInterval(interval);
            } else {
                if (data.count == document.getElementById('entrant_count').innerText) {
                    return;
                }
                document.getElementById('entrant_count').innerText = data.count;
                if (typeof data.numbers !== 'undefined') {
                    var numbersList = '';
                    for (var i = 0; i < data.numbers.length; i++) {
                        numbersList = numbersList + '<li>' + data.numbers[i] + '</li>';
                    }
                    document.getElementById('entrant_numbers').innerHTML = numbersList;
                }
            }
        })
    }, 2000));</script>" ?>

<?php require 'footer.php' ?>
