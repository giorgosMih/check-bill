<?php
session_name('checkBill_SESSID');
session_start();
require_once("includes/settings.php");

if (!isset($_SESSION['uid'])) {
    header('Location: ' . $app_root . '/login.php');
    die();
}

if( isset($_GET['logout']) ){
    session_unset();
    session_destroy();
    header('Refresh: 0');
    die();
}

if( $_SERVER['REQUEST_METHOD']=='POST' ){
    if( isset($_POST['btnAddBill']) ){
        require_once("includes/database.php");
        $billType = filter_var($_POST['selectBillType'], FILTER_SANITIZE_NUMBER_INT);
        $amount = filter_var($_POST['inputAmount'], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION);
        $releaseDate = $_POST['inputReleaseDate'];
        $expireDate = $_POST['inputExpireDate'];
        $payedDate = ((empty($_POST['inputPayedDate']))?null:$_POST['inputPayedDate']);

        $sql = '
        INSERT INTO bill(bill_type, uid, amount, release_date, expire_date, is_paid) 
        VALUES(?,?,?,?,?,?)
        ';
    
        $stmt = $dbConn->prepare($sql);
        $stmt->bind_param('iidsss', $billType, $_SESSION['uid'], $amount, $releaseDate, $expireDate, $payedDate);
        $stmt->execute();
    
        if($stmt->affected_rows != 1 )
            $_SESSION['message_code'] = -1;
        else
            $_SESSION['message_code'] = 1;
    
        $stmt->close();
        $dbConn->close();

        header('Location: '.$_SERVER['PHP_SELF']);
        die();
    }
}

if( isset($_GET['del-billid']) && !empty($_GET['del-billid']) ){
    require_once("includes/database.php");
    $id = filter_var($_GET['del-billid'], FILTER_SANITIZE_NUMBER_INT);
    $sql = 'DELETE FROM bill WHERE id=?';
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
 * -1: database error.
 * 0: no message.
 * 1: new bill added.
 * 2: bill deleted.
 */
$messageCode = 0;
if( isset($_SESSION['message_code']) ){
    $messageCode = $_SESSION['message_code'];
    unset($_SESSION['message_code']);
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

            $('#modalDeleteBill').on('hide.bs.modal',function(){
                $('#modalDeleteBill').removeData('bill-id');
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
                    "emptyTable": "Δεν έχετε καταχωρίσει κανένα λογαριασμό ακόμα!",
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
                    "targets": [0, 1, 5]
                }],
                "order": []
            });
        });

        function loadBill(id) {
            window.location.href = '<?php echo $app_root; ?>/bill.php?id=' + id;
        }

        function showdeleteBillModal(e,id){
            $('#modalDeleteBill').data('bill-id',id);
            $('#modalDeleteBill').modal('show');
            e.stopPropagation();
            e.preventDefault();
        }

        function deleteBill(){
            var id = $('#modalDeleteBill').data('bill-id');
            window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>?del-billid=' + id;
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
                    <li class="nav-item active">
                        <a class="nav-link" href="#">Λογαριασμοί <span class="sr-only">(current)</span></a>
                    </li>
                    <?php
                    if ($_SESSION['user_type'] != 1) {
                        if($_SESSION['user_type'] == 3){
                    ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $app_root; ?>/users.php">Χρήστες</a>
                        </li>
                        <?php
                        }
                        ?>
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
            if( $messageCode != -1 ){
               echo '<div class="alert alert-success alert-dismissible fade show mr-3 ml-3" role="alert">';
               if( $messageCode == 1 ){
                    echo '<strong>Επιτυχία!</strong> Ο νέος λογαριασμός αποθηκεύτηκε με επιτυχία.';
                }
                else if( $messageCode == 2){
                    echo '<strong>Επιτυχία!</strong> Ο λογαριασμός διαγράφηκε με επιτυχία.';
                }
            }
            else{
                echo '<div class="alert alert-danger alert-dismissible fade show mr-3 ml-3" role="alert">';
                echo '<strong>Σφάλμα Βάσης Δεδομένων!</strong> Η ενέργεια δεν μπόρεσε να ολοκληρωθεί. Δοκιμάστε ξανά.';
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
                <h4>Πίνακας Λογαριασμών</h4>
                <table class="datatables table table-light table-hover table-bordered text-center">
                    <thead class="thead-light">
                        <tr>
                            <th>
                                <button class="btn btn-outline-success m-0 p-0 pr-2 pl-2" data-toggle="modal" data-target="#modalAddBill">
                                    +
                                </button>
                            </th>
                            <th>#</th>
                            <th>Κατηγορία</th>
                            <th>Ποσό</th>
                            <th>Εκδόθηκε</th>
                            <th>Λήξη Πληρωμής</th>
                            <th>Πληρώθηκε</th>
                            <th>Πιθανή Ημ/νία<br />Επόμενου Λογαριασμού</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = 'SELECT * FROM v_bills WHERE uid=? ORDER BY id';
                        $stmt = $dbConn->prepare($sql);
                        $stmt->bind_param('i', $_SESSION['uid']);
                        $stmt->execute();

                        $result = $stmt->get_result();
                        if ($result->num_rows > 0) {
                            $index = 1;
                            while ($row = $result->fetch_assoc()) {
                                //release date fix
                                $tokens = explode("-", $row['release_date']);
							    if(sizeof($tokens) == 3){
								    $fixedReleaseDate = $tokens[2]."-".$tokens[1]."-".$tokens[0];
							    }
							    else{
								    $fixedReleaseDate = "-";
							    }
                                
                                //expire date fix
                                $tokens = explode("-", $row['expire_date']);
							    if(sizeof($tokens) == 3){
								    $fixedExpDate = $tokens[2]."-".$tokens[1]."-".$tokens[0];
							    }
							    else{
								    $fixedExpDate = "-";
                                }
                                
                                //next bill date fix
                                $tokens = explode("-", $row['till_next_bill']);
							    if(sizeof($tokens) == 3){
								    $fixedNextDate = $tokens[2]."-".$tokens[1]."-".$tokens[0];
							    }
							    else{
								    $fixedNextDate = "-";
                                }
                                
                                //payed date fix
                                $tokens = explode("-", $row['is_paid']);
							    if(sizeof($tokens) == 3){
								    $fixedPayedDate = $tokens[2]."-".$tokens[1]."-".$tokens[0];
							    }
							    else{
								    $fixedPayedDate = "-";
							    }

                                echo '
                                <tr onclick="loadBill(' . $row['id'] . ')">
                                    <td>
                                        <button onclick="showdeleteBillModal(event,'.$row['id'].')" class="btn btn-outline-danger m-0 p-0 pr-2 pl-2" title="Διαγραφή">x</button>
                                    </td>
                                    <td>' . $index . '</td>
                                    <td>' . $row['description'] . '</td>
                                    <td>' . $row['amount'] . '</td>
                                    <td>' . $fixedReleaseDate . '</td>
                                    <td>' . $fixedExpDate . '</td>
                                    <td>' . $fixedPayedDate. '</td>
                                    <td>' . $fixedNextDate . '</td>
                                </tr>
                                ';
                                $index++;
                            }
                        }
                        $stmt->close();
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- /content -->
    </div>

    </div>

    <!-- modals -->
    <!-- modal add new bill -->
    <div id="modalAddBill" tabindex="-1" class="modal fade" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Προσθήκη Νέου Λογαριασμού</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form id="formAddBill" method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
                        <div class="form-row">
                            <div class="form-group col">
                                <label for="selectBillTypeControl">Κατηγορία Λογαριασμού</label>
                                <select id="selectBillTypeControl" class="custom-select" name="selectBillType">
                                    <?php
                                    $sql = 'SELECT * FROM bill_type';
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
                                <label for="inputAmountControl">Ποσό</label>
                                <input id="inputAmountControl" class="form-control" type="number" step="0.01" name="inputAmount" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col align-self-center">
                                <label for="inputReleaseDateControl">Εκδόθηκε</label>
                                <input id="inputReleaseDateControl" class="form-control" type="date" name="inputReleaseDate" required>
                            </div>
                            <div class="form-group col">
                                <label for="inputExpireDateControl">Λήξη Πληρωμής</label>
                                <input id="inputExpireDateControl" class="form-control" type="date" name="inputExpireDate" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col">
                                <label for="inputPayedDateControl">Πληρώθηκε</label>
                                <input id="inputPayedDateControl" class="form-control" type="date" name="inputPayedDate">
                            </div>
                        </div>
                    </form>

                </div>
                <div class="modal-footer">
                    <button type="submit" form="formAddBill" class="btn btn-primary" name="btnAddBill">Αποθήκευση</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Άκυρο</button>
                </div>
            </div>
        </div>
    </div>
    <!-- /modal add new bill -->

    <!-- modal delete bill -->
    <div id="modalDeleteBill" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="deleteBillModal" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteBillModal">Διαγραφή Λογαριασμού</h5>
                    <button class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Είστε σίγουροι οτι θέλετε να διαγράψετε αυτόν τον Λογαριασμό;</p>
                </div>
                <div class="modal-footer">
                    <button onclick="deleteBill()" class="btn btn-danger">Διαγραφή</button>
                    <button class="btn btn-secondary" data-dismiss="modal">Άκυρο</button>
                </div>
            </div>
        </div>
    </div>
    <!-- /modal delete bill -->

</body>

</html>
<?php
$dbConn->close();
?>