<?php require 'header.php' ?>

<div class="jumbotron">
    <div class="container">
        <h1>Start a raffle</h1>
        <p></p>
        <form method="post">
            <div class="form-group<?= isset($errors) && isset($errors['raffle_name']) ? ' has-error' : '' ?>">
                <label for="raffle_name">Enter the name of your raffle</label></p>
                <input class="form-control" type="text" name="raffle_name" value="<?= isset($raffleName) ? $raffleName : '' ?>"></p>
            </div>
            <div class="form-group<?= isset($errors) && isset($errors['raffle_items']) ? ' has-error' : '' ?>">
                <label for="raffle_items">Enter the items to raffle, one per line.</label></p>
                <p><textarea name="raffle_items" class="form-control" rows="10"><?= isset($raffleItems) ? $raffleItems : '' ?></textarea></p>
            </div>
            <p><input type="submit" class="btn btn-primary btn-lg" href="#" role="button" value="Start Raffle" /></p>
        </form>
    </div>
</div>

<?php require 'footer.php' ?>
