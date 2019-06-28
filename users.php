<?php
session_name('checkBill_SESSID');
session_start();
require_once("includes/settings.php");

if (!isset($_SESSION['uid']) ) {
    header('Location: ' . $app_root . '/login.php');
    die();
}

if ($_SESSION['user_type'] !=3 ) {
    echo '<script>history.back();</script>';
    die();
}

if( isset($_GET['logout']) ){
    session_unset();
    session_destroy();
    header('Refresh: 0');
    die();
}

if( $_SERVER['REQUEST_METHOD']=='POST' ){
    if( isset($_POST['btnAddUser']) ){
        require_once("includes/database.php");
        $userType = filter_var($_POST['selectUserType'], FILTER_SANITIZE_NUMBER_INT);
        $username = filter_var($_POST['inputUsername'], FILTER_SANITIZE_STRING);
        $password = filter_var($_POST['inputPassword'], FILTER_SANITIZE_STRING);
        $lastName = filter_var($_POST['inputLastname'], FILTER_SANITIZE_STRING);
        $firstname = filter_var($_POST['inputFirstname'], FILTER_SANITIZE_STRING);

        $sql = '
        INSERT INTO user(user_type, username, password) 
        VALUES(?,?,?)
        ';
    
        $stmt = $dbConn->prepare($sql);
        $stmt->bind_param('iss', $userType, $username, password_hash($password, PASSWORD_BCRYPT));
        $stmt->execute();
    
        if($stmt->affected_rows != 1 ){
            if($stmt->errno == 1062){
                $_SESSION['message_code'] = -2;
                $_SESSION['message_data'] = $username;
            }
            else
                $_SESSION['message_code'] = -1;
        }
        else{
            $newID = $stmt->insert_id;
            $stmt->close();
            $sql = '
            INSERT INTO user_account(id, last_name, first_name) 
            VALUES(?,?,?)
            ';
    
            $stmt = $dbConn->prepare($sql);
            $stmt->bind_param('iss', $newID, $lastName, $firstname);
            $stmt->execute();

            if($stmt->affected_rows != 1 )
                $_SESSION['message_code'] = -1;
            else
                $_SESSION['message_code'] = 1;
        }
    
        $stmt->close();
        $dbConn->close();

        header('Location: '.$_SERVER['PHP_SELF']);
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

    header('Location: '.$_SERVER['PHP_SELF']);
    die();
}

require_once("includes/database.php");
/**
 * Code to display message after any update.
 * -2: username exists
 * -1: database error.
 * 0: no message.
 * 1: new user added.
 * 2: user deleted.
 */
$messageCode = 0;
$messageData = null;
if( isset($_SESSION['message_code']) ){
    $messageCode = $_SESSION['message_code'];
    $messageData = (isset($_SESSION['message_data'])?$_SESSION['message_data']:null);
    unset($_SESSION['message_code']);
    unset($_SESSION['message_data']);
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
    <link rel="stylesheet" href="modules/datatables/datatables.css">
    <script src="modules/datatables/datatables.js"></script>
    <script>
        $(document).ready(function() {
            var alert = $('.alert');
            if(alert){
                $(alert).delay(5000).fadeOut(800);
            }

            $('#modalDeleteUser').on('hide.bs.modal',function(){
                $('#modalDeleteUser').removeData('uid');
            });

            $('#formAddUser').submit(function(e){
                var pass = $('#inputPasswordControl').val();
                var passVer = $('#inputPasswordVerifyControl').val();

                if(pass != passVer){
                    e.preventDefault();
                    $('#formAddUserMessage').removeClass('hidden');
                }
            });

            $('.datatables').DataTable({
                "paging": true,
                "lengthChange": false,
                "pageLength": 12,
                "autoWidth": true,
                "processing": true,
                "pagingType": "full_numbers",
                "language": {
                    "decimal": ",",
                    "thousands": ".",
                    "emptyTable": "Δεν υπάρχουν άλλοι χρήστες!",
                    "info": "Σελίδα _START_ από _END_ (Σύνολο: _TOTAL_ εγγραφές)",
                    "infoEmpty": "Σελίδα 0 από 0 (Συνολικά: 0 εγγραφές)",
                    "infoFiltered": "(αναζήτηση από _MAX_ συνολικά εγγραφές)",
                    "infoPostFix": "",
                    "lengthMenu": "Εμφάνισε _MENU_ εγγραφές",
                    "loadingRecords": "Loading...",
                    "processing": "Processing...",
                    "search": "Αναζήτηση:",
                    "zeroRecords": "Η αναζήτηση δεν βρήκε τίποτα. Δοκιμάστε ξανά.",
                    "paginate": {
                        "first": "Πρώτο",
                        "last": "Τελευταίο",
                        "next": "Επόμενο",
                        "previous": "Προηγούμενο"
                    },
                    "aria": {
                        "sortAscending": ": activate to sort column ascending",
                        "sortDescending": ": activate to sort column descending"
                    }
                },
                "columnDefs": [{
                    "orderable": false,
                    "searchable": false,
                    "targets": [0, 1]
                }],
                "order": []
            });
        });

        function loadUser(id) {
            window.location.href = '<?php echo $app_root; ?>/user.php?id=' + id;
        }

        function showDeleteUserModal(e,id){
            $('#modalDeleteUser').data('uid',id);
            $('#modalDeleteUser').modal('show');
            e.stopPropagation();
            e.preventDefault();
        }

        function deleteUser(){
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
                            <a class="nav-link" href="#">Χρήστες <span class="sr-only">(current)</span></a>
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
                            <a class="dropdown-item" href="<?php echo $_SERVER['PHP_SELF'];?>?logout">Έξοδος</a>
                        </div>
                    </div>
                </ul>
            </div>
        </nav>
        
        <!-- content -->
        <div class="main-content">
            <br/>
        <?php
        if ($messageCode != 0) {
            if( $messageCode > 0 ){
               echo '<div class="alert alert-success alert-dismissible fade show mr-3 ml-3" role="alert">';
               if( $messageCode == 1 ){
                    echo '<strong>Επιτυχία!</strong> Ο νέος χρήστης αποθηκεύτηκε με επιτυχία.';
                }
                else if( $messageCode == 2){
                    echo '<strong>Επιτυχία!</strong> Ο χρήστης διαγράφηκε με επιτυχία.';
                }
            }
            else{
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
                <h4>Πίνακας Χρηστών</h4>
                <table class="datatables table table-light table-hover table-bordered text-center">
                    <thead class="thead-light">
                        <tr>
                            <th>
                                <button class="btn btn-outline-success m-0 p-0 pr-2 pl-2" data-toggle="modal" data-target="#modalAddUser">
                                    +
                                </button>
                            </th>
                            <th>#</th>
                            <th>Κατηγορία</th>
                            <th>Όνομα Χρήστη</th>
                            <th>Επώνυμο</th>
                            <th>Όνομα</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = '
                        SELECT u.uid,t.description,u.username,a.last_name,a.first_name 
                        FROM user u, user_account a, user_type t 
                        WHERE u.uid=a.id AND t.id=u.user_type AND u.uid != '.$_SESSION['uid'].'
                        ORDER BY 3
                        ';
                        $result = $dbConn->query($sql);
                        if ($result->num_rows > 0) {
                            $index = 1;
                            while ($row = $result->fetch_assoc()) {
                                echo '
                                <tr onclick="loadUser(' . $row['uid'] . ')">
                                    <td>
                                        <button onclick="showDeleteUserModal(event,'.$row['uid'].')" class="btn btn-outline-danger m-0 p-0 pr-2 pl-2" title="Διαγραφή">x</button>
                                    </td>
                                    <td>' . $index . '</td>
                                    <td>' . $row['description'] . '</td>
                                    <td>' . $row['username'] . '</td>
                                    <td>' . $row['last_name'] . '</td>
                                    <td>' . $row['first_name'] . '</td>
                                </tr>
                                ';
                                $index++;
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- /content -->
    </div>

    </div>

    <!-- modals -->

    <!-- modal add new user -->
    <div id="modalAddUser" tabindex="-1" class="modal fade" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Προσθήκη Νέου Χρήστη</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form id="formAddUser" method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
                        <div class="form-row">
                            <div class="form-group col">
                                <label for="selectBillTypeControl">Κατηγορία Χρήστη</label>
                                <select id="selectBillTypeControl" class="custom-select" name="selectUserType">
                                    <?php
                                    $sql = 'SELECT * FROM user_type';
                                    $result = $dbConn->query($sql);
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo '<option value="' . $row['id'] . '">' . $row['description'] . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group col">
                                <label for="inputUsernameControl">Όνομα Χρήστη</label>
                                <input id="inputUsernameControl" class="form-control" type="text" name="inputUsername" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col align-self-center">
                                <label for="inputPasswordControl">Κωδικός</label>
                                <input id="inputPasswordControl" class="form-control" type="password" name="inputPassword" required>
                            </div>
                            <div class="form-group col">
                                <label for="inputPasswordVerifyControl">Επιβεβαίωση Κωδικού</label>
                                <input id="inputPasswordVerifyControl" class="form-control" type="password" required>
                            </div>
                        </div>
                        <hr />
                        <div class="form-row">
                            <div class="form-group col">
                                <label for="inputLastnameControl">Επώνυμο</label>
                                <input id="inputLastnameControl" class="form-control" type="text" name="inputLastname" required>
                            </div>
                            <div class="form-group col">
                                <label for="inputFirstnameControl">Όνομα</label>
                                <input id="inputFirstnameControl" class="form-control" type="text" name="inputFirstname" required>
                            </div>
                        </div>
                    </form>

                    <div id="formAddUserMessage" class="hidden text-danger">Τα πεδία 'Κωδικός' και 'Επιβεβαίωση Κωδικού' δεν ταιριάζουν. Δοκιμάστε ξανά.</div>
                </div>
                <div class="modal-footer">
                    <button type="submit" form="formAddUser" class="btn btn-primary" name="btnAddUser">Αποθήκευση</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Άκυρο</button>
                </div>
            </div>
        </div>
    </div>
    <!-- /modal add new user -->

    <!-- modal delete user -->
    <div id="modalDeleteUser" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="deleteUserModal" aria-hidden="true">
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

</body>

</html>
<?php
$dbConn->close();
?>