<?php
session_name('checkBill_SESSID');
session_start();
require_once("includes/settings.php");

if (!isset($_SESSION['uid'])) {
    header('Location: ' . $app_root . '/login.php');
    die();
}

if ($_SESSION['user_type'] != 3) {
    echo '<script>history.back();</script>';
    die();
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Refresh: 0');
    die();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['btnUpdateUser'])) {
        require_once("includes/database.php");
        $id = filter_var($_POST['userid'], FILTER_SANITIZE_NUMBER_INT);
        $userType = filter_var($_POST['selectUserType'], FILTER_SANITIZE_NUMBER_INT);
        $username = filter_var($_POST['inputUsername'], FILTER_SANITIZE_STRING);
        $lastName = filter_var($_POST['inputLastname'], FILTER_SANITIZE_STRING);
        $firstname = filter_var($_POST['inputFirstname'], FILTER_SANITIZE_STRING);

        $sql = '
        UPDATE user 
        SET user_type=?,username=? 
        WHERE uid=?
        ';

        $stmt = $dbConn->prepare($sql);
        $stmt->bind_param('isi', $userType, $username, $id);
        $stmt->execute();

        if ($stmt->error) {
            if ($stmt->errno == 1062){
                $_SESSION['message_code'] = -2;
                $_SESSION['message_data'] = $username;
            }
            else
                $_SESSION['message_code'] = -1;
        } else {
            $stmt->close();
            $sql = '
            UPDATE user_account 
            SET last_name=?,first_name=? 
            WHERE id=?
            ';

            $stmt = $dbConn->prepare($sql);
            $stmt->bind_param('ssi', $lastName, $firstname, $id);
            $stmt->execute();

            if ($stmt->error) {
                $_SESSION['message_code'] = -1;
            }
            else{
                $_SESSION['message_code'] = 1;
            }
        }

        $stmt->close();
        $dbConn->close();

        header('Location: ' . $app_root . '/user.php?id=' . $id);

        die();
    }
    else if( isset($_POST['btnChangePassword']) ){
        require_once("includes/database.php");
        $id = filter_var($_POST['userid'], FILTER_SANITIZE_NUMBER_INT);
        $password = filter_var($_POST['inputPassword'], FILTER_SANITIZE_STRING);
        $password = password_hash($password, PASSWORD_BCRYPT);

        $sql = '
        UPDATE user 
        SET password=? 
        WHERE uid=?
        ';

        $stmt = $dbConn->prepare($sql);
        $stmt->bind_param('si', $password, $id);
        $stmt->execute();

        if ($stmt->error) {
            $_SESSION['message_code'] = -1;
        } else {
            $_SESSION['message_code'] = 3;
        }

        $stmt->close();
        $dbConn->close();

        header('Location: ' . $app_root . '/user.php?id=' . $id);
        die();
    }
}

if( isset($_GET['del-uid']) && !empty($_GET['del-uid']) ){
    require_once("includes/database.php");
    $id = filter_var($_GET['del-uid'], FILTER_SANITIZE_NUMBER_INT);
    $sql = 'DELETE FROM user WHERE uid=?';
    $stmt = $dbConn->prepare($sql);
    $stmt->bind_param('i',$id);
    $stmt->execute();

    if($stmt->affected_rows != 1 )
        $_SESSION['message_code'] = -1;
    else
        $_SESSION['message_code'] = 2;
    
    $stmt->close();
    $dbConn->close();

    header('Location: ' . $app_root . '/users.php');
    die();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ' . $app_root . '/users.php');
    die();
}

require_once("includes/database.php");
/**
 * Code to display message after any update.
 * -2: username exists
 * -1: database error.
 * 0: no message.
 * 1: user updated successfully.
 * 2: user deleted.
 * 3: password changed
 */
$messageCode = 0;
$messageData = null;
if (isset($_SESSION['message_code'])) {
    $messageCode = $_SESSION['message_code'];
    $messageData = (isset($_SESSION['message_data'])?$_SESSION['message_data']:null);
    unset($_SESSION['message_code']);
    unset($_SESSION['message_data']);
}
$id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
$sql = '
SELECT u.uid, u.user_type, u.username, ac.last_name, ac.first_name,
(SELECT uid FROM user WHERE username>u.username AND uid != ? ORDER BY username LIMIT 1) as next_id, 
(SELECT uid FROM user WHERE username<u.username AND uid != ? ORDER BY username DESC LIMIT 1) as prev_id 
FROM user u, user_account ac 
WHERE u.uid=? AND ac.id=u.uid
';
$stmt = $dbConn->prepare($sql);
$stmt->bind_param('iii', $_SESSION['uid'], $_SESSION['uid'], $id);
$stmt->execute();

$result = $stmt->get_result();
$stmt->close();
if ($result->num_rows != 1) {
    echo 'Δεν υπάρχει χρήστης με ID: ' . $id;
    die();
}

$userData = $result->fetch_assoc();
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
    <link rel="stylesheet" href="modules/datatables/datatables.css">
    <script src="modules/datatables/datatables.js"></script>
    <style>
        .buttons a {
            margin: 5px;
        }

        .buttons button {
            margin: 2px;
        }

        .main-content {
            overflow: auto;
            height: calc(100% - 105px) !important;
        }
    </style>
    <script>
        $(document).ready(function() {
            var alert = $('.alert');
            if (alert) {
                $(alert).delay(5000).fadeOut(800);
            }

            $('#formChangePassword').submit(function(e){
                var pass = $('#inputPasswordControl').val();
                var passVer = $('#inputPasswordVerifyControl').val();

                if(pass != passVer){
                    e.preventDefault();
                    $('#formChangePasswordMessage').removeClass('hidden');
                }
            });

            $('#modalDeleteUser').on('hide.bs.modal',function(){
                $('#modalDeleteUser').removeData('uid');
            });

        });

        function showdeleteUserModal(e, id) {
            $('#modalDeleteUser').data('uid', id);
            $('#modalDeleteUser').modal('show');
            e.stopPropagation();
            e.preventDefault();
        }

        function deleteUser() {
            var id = $('#modalDeleteUser').data('uid');
            window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>?del-uid=' + id;
        }
    </script>
</head>

<body>

    <div class="container-fluid">

        <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
            <a class="navbar-brand text-light"><?php echo $app_name; ?></a>
            <button class="navbar-toggler" data-target="#my-nav" data-toggle="collapse" aria-controls="my-nav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div id="my-nav" class="collapse navbar-collapse">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $app_root; ?>">Αρχική</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $app_root; ?>/bills.php">Λογαριασμοί</a>
                    </li>
                    <?php
                    if ($_SESSION['user_type'] == 3) {
                        ?>
                        <li class="nav-item active">
                            <a class="nav-link" href="<?php echo $app_root; ?>/users.php">Χρήστες <span class="sr-only">(current)</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $app_root; ?>/system-settings.php">Ρυθμίσεις</a>
                        </li>
                    <?php
                }
                ?>
                </ul>
                <ul class="navbar-nav ml-auto">
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <?php echo $_SESSION['displayname']; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                            <a class="dropdown-item" href="<?php echo $app_root; ?>/account.php">Λογαριασμός</a>
                            <a class="dropdown-item" href="<?php echo $_SERVER['PHP_SELF']; ?>?logout">Έξοδος</a>
                        </div>
                    </div>
                </ul>
            </div>
        </nav>

        <div class="row m-0 p-0">
            <div class="col-12 buttons">
                <?php
                if ($userData['prev_id'] != null)
                    echo '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $userData['prev_id'] . '" class="btn btn-outline-success"><img src="' . $app_root . '/img/left_arrow.png"></a>';
                else
                    echo '<a href="#" class="btn btn-outline-success disabled"><img src="' . $app_root . '/img/left_arrow.png"></a>';

                if ($userData['next_id'] != null)
                    echo '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $userData['next_id'] . '" class="btn btn-outline-success"><img src="' . $app_root . '/img/right_arrow.png"></a>';
                else
                    echo '<a href="#" class="btn btn-outline-success disabled"><img src="' . $app_root . '/img/right_arrow.png"></a>';
                ?>
                <button type="submit" form="formUpdateUser" class="btn btn-outline-primary" name="btnUpdateUser" title="Αποθήκευση">
                    <img src="<?php echo $app_root; ?>/img/save.png">
                </button>
                <button type="button" onclick="showdeleteUserModal(event, <?php echo $id;?>)" class="btn btn-outline-danger" title="Διαγραφή">
                    <img src="<?php echo $app_root; ?>/img/cancel.png">
                </button>
                <button type="button" class="btn btn-outline-warning" title="Αλλαγή Κωδικού" data-toggle="modal" data-target="#modalChangePassword">
                    <img src="<?php echo $app_root; ?>/img/edit.png">
                </button>
            </div>
        </div>

        <!-- content -->
        <div class="main-content">
            <?php
            if ($messageCode != 0) {
                if ($messageCode > 0) {
                    echo '<div class="alert alert-success alert-dismissible fade show mr-3 ml-3" role="alert">';
                    if ($messageCode == 1) {
                        echo '<strong>Επιτυχία!</strong> Ο χρήστης ενημερώθηκε με επιτυχία.';
                    } else if ($messageCode == 2) {
                        echo '<strong>Επιτυχία!</strong> Ο λογαριασμός διαγράφηκε με επιτυχία.';
                    } else if ($messageCode == 3) {
                        echo '<strong>Επιτυχία!</strong> Ο κωδικός άλλαξε με επιτυχία.';
                    }
                } else {
                    echo '<div class="alert alert-danger alert-dismissible fade show mr-3 ml-3" role="alert">';
                    if( $messageCode == -1 )
                        echo '<strong>Σφάλμα Βάσης Δεδομένων!</strong> Η ενέργεια δεν μπόρεσε να ολοκληρωθεί. Δοκιμάστε ξανά.';
                    else if( $messageCode == -2 )
                        echo '<strong>Σφάλμα!</strong> Το Όνομα Χρήστη "'.$messageData.'" υπάρχει ήδη. Εισάγετε ένα "Όνομα Χρήστη" που να μην υπάρχει.';
                }
                ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php
    }
    ?>

        <div class="row m-0 p-0">
            <div class="col-12">
                <h4>Χρήστης με ID: <?php echo $id; ?></h4>
                <br />
                <form id="formUpdateUser" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <div class="form-row">
                        <div class="form-group col">
                            <label for="selectBillTypeControl">Κατηγορία Χρήστη</label>
                            <select id="selectBillTypeControl" class="custom-select" name="selectUserType">
                                <?php
                                $sql = 'SELECT * FROM user_type';
                                $result = $dbConn->query($sql);
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo '<option ' . (($row['id'] == $userData['user_type']) ? "selected" : "") . ' value="' . $row['id'] . '">' . $row['description'] . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group col">
                            <label for="inputUsernameControl">Όνομα Χρήστη</label>
                            <input id="inputUsernameControl" class="form-control" type="text" name="inputUsername" value="<?php echo $userData['username']; ?>" required>
                        </div>
                    </div>
                    <hr />
                    <div class="h5 font-italic">Επωνυμία Χρήστη</div>
                    <div class="form-row">
                        <div class="form-group col">
                            <label for="inputLastnameControl">Επώνυμο</label>
                            <input id="inputLastnameControl" class="form-control" type="text" name="inputLastname" value="<?php echo $userData['last_name']; ?>" required>
                        </div>
                        <div class="form-group col">
                            <label for="inputFirstnameControl">Όνομα</label>
                            <input id="inputFirstnameControl" class="form-control" type="text" name="inputFirstname" value="<?php echo $userData['first_name']; ?>" required>
                        </div>
                    </div>
                    <input type="hidden" name="userid" value="<?php echo $userData['uid']; ?>">
                </form>
            </div>
        </div>
        <!-- /content -->
    </div>

    </div>

    <!-- modals -->

    <!-- modal delete user -->
    <div id="modalDeleteUser" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="deleteBillModal" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteBillModal">Διαγραφή Χρήστη</h5>
                    <button class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Είστε σίγουροι οτι θέλετε να διαγράψετε αυτόν τον χρήστη;</p>
                </div>
                <div class="modal-footer">
                    <button onclick="deleteUser()" class="btn btn-danger">Διαγραφή</button>
                    <button class="btn btn-secondary" data-dismiss="modal">Άκυρο</button>
                </div>
            </div>
        </div>
    </div>
    <!-- /modal delete user -->

    <!-- modal change password -->
    <div id="modalChangePassword" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="changePasswordModal" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModal">Αλλαγή Κωδικού Χρήστη: <?php echo $userData['username'];?></h5>
                    <button class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formChangePassword" method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
                        <div class="form-row">
                            <div class="form-group col">
                                <label for="inputPasswordControl">Κωδικός</label>
                                <input id="inputPasswordControl" class="form-control" type="password" name="inputPassword" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col">
                                <label for="inputPasswordVerifyControl">Επιβεβαίωση Κωδικού</label>
                                <input id="inputPasswordVerifyControl" class="form-control" type="password" required>
                            </div>
                        </div>
                        <input type="hidden" name="userid" value="<?php echo $id;?>">
                    </form>

                    <div id="formChangePasswordMessage" class="hidden text-danger">Τα πεδία 'Κωδικός' και 'Επιβεβαίωση Κωδικού' δεν ταιριάζουν. Δοκιμάστε ξανά.</div>
                </div>
                <div class="modal-footer">
                    <button type="submit" form="formChangePassword" class="btn btn-warning" name="btnChangePassword">Αλλαγή</button>
                    <button class="btn btn-secondary" data-dismiss="modal">Άκυρο</button>
                </div>
            </div>
        </div>
    </div>
    <!-- /modal change password -->

</body>

</html>
<?php
$dbConn->close();
?>