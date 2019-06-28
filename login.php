<?php
session_name('checkBill_SESSID');
session_start();
require_once("includes/settings.php");

if (isset($_SESSION['uid'])) {
    header('Location: ' . $app_root);
    die();
}

/**
 * errorCode for login.
 * 0: ok
 * 1: username not found
 * 2: wrong password
 */
$errorCode = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['btnLogin'])) {
        require_once('includes/database.php');
        $username = filter_var($_POST['inputUsername'], FILTER_SANITIZE_STRING);
        $password = filter_var($_POST['inputPassword'], FILTER_SANITIZE_STRING);

        $sql = 'SELECT * FROM v_users WHERE username=?';

        $stmt = $dbConn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $_SESSION['uid'] = $row['uid'];
                $_SESSION['username'] = $username;
                $_SESSION['user_type'] = $row['user_type'];
                $_SESSION['displayname'] = $row['first_name'] . ' ' . $row['last_name'];

                $stmt->close();
                $dbConn->close();
                header('Location: ' . $app_root);
                die();
            } else {
                $errorCode = 2;
            }
        } else {
            $errorCode = 1;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="el">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Bill Checker">
    <title><?php echo $app_name; ?></title>
    <link rel="stylesheet" href="css/bootstrap-min.css">
    <link rel="stylesheet" href="css/main.css">
    <script src="js/jquery-3.3.1.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
</head>

<body>

    <div class="container-fluid">

        <?php
        if ($errorCode != 0) {
            ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php
                if ($errorCode == 1)
                    echo '<strong>Σφάλμα Σύνδεσης:</strong> Ο χρήστης "' . $username . '" δεν υπάρχει. Δοκομάστε ξανά.';
                else {
                    echo '<strong>Σφάλμα Σύνδεσης:</strong> Ο κωδικός που πληκτρολογίσατε είναι λανθασμένος. Δοκομάστε ξανά.';
                }
                ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php
        }
        ?>

        <h2 class="text-center pt-4">Συνδεθείτε στο <?php echo $app_name; ?></h2>
        <br />
        <form class="login-form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <div class="form-group">
                <label for="inputUsernameControl">Όνομα Χρήστη</label>
                <input id="inputUsernameControl" class="form-control" type="text" name="inputUsername" required>
            </div>
            <div class="form-group">
                <label for="inputPasswordControl">Κωδικός</label>
                <input id="inputPasswordControl" class="form-control" type="password" name="inputPassword" required>
            </div>
            <button class="btn btn-primary" type="submit" name="btnLogin">Σύνδεση</button>
        </form>

    </div>

</body>

</html>