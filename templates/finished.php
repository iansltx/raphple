<?php require 'header.php' ?>

<div class="jumbotron">
    <div class="container">
        <h1>Raffle complete</h1>
        <p><?= $raffleName ?> has been closed and entrants have been texted.</p>
        <?php if (isset($winnerNumbers)): ?>
            <h3>Winners</h3>
            <ul id="winner_numbers">
                <?php foreach ($winnerNumbers as $number): ?>
                    <li><?= $maskNumber($number) ?></li>
                <?php endforeach ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php' ?>
