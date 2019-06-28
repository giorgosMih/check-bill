<?php
session_name('checkBill_SESSID');
session_start();
require_once("includes/settings.php");

if (!isset($_SESSION['uid'])) {
    header('Location: ' . $app_root . '/login.php');
    die();
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Refresh: 0');
    die();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if( isset($_POST['btnUpdateBill']) ){
        require_once("includes/database.php");
        $id = filter_var($_POST['bill-id'],FILTER_SANITIZE_NUMBER_INT);
        $billType = filter_var($_POST['selectBillType'], FILTER_SANITIZE_NUMBER_INT);
        $amount = filter_var($_POST['inputAmount'], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION);
        $releaseDate = $_POST['inputReleaseDate'];
        $expireDate = $_POST['inputExpireDate'];
        $payedDate = ((empty($_POST['inputPayedDate']))?null:$_POST['inputPayedDate']);

        $sql = '
        UPDATE bill 
        SET bill_type=?,amount=?,release_date=?,expire_date=?,is_paid=? 
        WHERE id=?
        ';

        $stmt = $dbConn->prepare($sql);
        $stmt->bind_param('idsssi', $billType, $amount, $releaseDate, $expireDate, $payedDate, $id);
        $stmt->execute();

        if($stmt->error){
            $_SESSION['message_code'] = -1;
        }
        else{
            $_SESSION['message_code'] = 1;
        }
    
        $stmt->close();
        $dbConn->close();

        header('Location: ' . $app_root . '/bill.php?id='.$id);

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

    header('Location: ' . $app_root . '/bills.php');
    die();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ' . $app_root . '/bills.php');
    die();
}

require_once("includes/database.php");
/**
 * Code to display message after any update.
 * -1: database error.
 * 0: no message.
 * 1: bill updated successfully.
 * 2: bill deleted.
 */
$messageCode = 0;
if (isset($_SESSION['message_code'])) {
    $messageCode = $_SESSION['message_code'];
    unset($_SESSION['message_code']);
}
$id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
$sql = '
SELECT b.*, 
(SELECT id FROM bill WHERE id>b.id AND uid=? LIMIT 1) as next_id, 
(SELECT id FROM bill WHERE id<b.id AND uid=? ORDER BY id DESC LIMIT 1) as prev_id 
FROM bill b 
WHERE b.id=?';
$stmt = $dbConn->prepare($sql);
$stmt->bind_param('iii', $_SESSION['uid'],$_SESSION['uid'],$id);
$stmt->execute();

$result = $stmt->get_result();
$stmt->close();
if( $result->num_rows != 1 ){
    echo 'Δεν υπάρχει λογαριασμός με ID: '.$id;
    die();
}

$billData = $result->fetch_assoc();
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
        .buttons a{
            margin: 5px;
        }

        .buttons button{
            margin: 2px;
        }

        .main-content{
            overflow: auto;
            height: calc(100% - 105px)!important;
        }
    </style>
    <script>
        $(document).ready(function() {
            var alert = $('.alert');
            if (alert) {
                $(alert).delay(5000).fadeOut(800);
            }

            $('#modalDeleteBill').on('hide.bs.modal',function(){
                $('#modalDeleteBill').removeData('bill-id');
            });

        });

        function showdeleteBillModal(e, id) {
            $('#modalDeleteBill').data('bill-id', id);
            $('#modalDeleteBill').modal('show');
            e.stopPropagation();
            e.preventDefault();
        }

        function deleteBill() {
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
                        <a class="nav-link" href="<?php echo $app_root; ?>/bills.php">Λογαριασμοί <span class="sr-only">(current)</span></a>
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
                            <a class="dropdown-item" href="<?php echo $_SERVER['PHP_SELF']; ?>?logout">Έξοδος</a>
                        </div>
                    </div>
                </ul>
            </div>
        </nav>

        <div class="row m-0 p-0">
            <div class="col-12 buttons">
                <?php
                    if( $billData['prev_id'] != null)
                        echo '<a href="'.$_SERVER['PHP_SELF'].'?id='.$billData['prev_id'].'" class="btn btn-outline-success"><img src="'.$app_root.'/img/left_arrow.png"></a>';
                    else
                        echo '<a href="#" class="btn btn-outline-success disabled"><img src="'.$app_root.'/img/left_arrow.png"></a>';

                    if( $billData['next_id'] != null)
                        echo '<a href="'.$_SERVER['PHP_SELF'].'?id='.$billData['next_id'].'" class="btn btn-outline-success"><img src="'.$app_root.'/img/right_arrow.png"></a>';
                    else
                        echo '<a href="#" class="btn btn-outline-success disabled"><img src="'.$app_root.'/img/right_arrow.png"></a>';
                ?>
                <button type="submit" form="formUpdateBill" class="btn btn-outline-primary" name="btnUpdateBill">
                <img src="<?php echo $app_root;?>/img/save.png">
                </button>
                <button type="button" class="btn btn-outline-danger" title="Διαγραφή" onclick="showdeleteBillModal(event, <?php echo $id;?>)">
                    <img src="<?php echo $app_root;?>/img/cancel.png">
                </button>
            </div>
        </div>

        <!-- content -->
        <div class="main-content">
            <?php
            if ($messageCode != 0) {
                if ($messageCode != -1) {
                    echo '<div class="alert alert-success alert-dismissible fade show mr-3 ml-3" role="alert">';
                    if ($messageCode == 1) {
                        echo '<strong>Επιτυχία!</strong> Ο λογαριασμός ενημερώθηκε με επιτυχία.';
                    }
                } else {
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
                <h4>Λογαριασμός με ID: <?php echo $id;?></h4>
                <br />
                <form id="formUpdateBill" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <div class="form-row">
                        <div class="form-group col">
                            <label for="selectBillTypeControl">Κατηγορία Λογαριασμού</label>
                            <select id="selectBillTypeControl" class="custom-select" name="selectBillType">
                                <?php
                                $sql = 'SELECT * FROM bill_type';
                                $result = $dbConn->query($sql);
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo '<option '.(($row['id']==$billData['bill_type'])?"selected":"").'  value="' . $row['id'] . '">' . $row['description'] . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group col">
                            <label for="inputAmountControl">Ποσό</label>
                            <input id="inputAmountControl" class="form-control" type="number" step="0.01" name="inputAmount" value="<?php echo $billData['amount'];?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col align-self-center">
                            <label for="inputReleaseDateControl">Εκδόθηκε</label>
                            <input id="inputReleaseDateControl" class="form-control" type="date" name="inputReleaseDate" value="<?php echo $billData['release_date'];?>" required>
                        </div>
                        <div class="form-group col">
                            <label for="inputExpireDateControl">Λήξη Πληρωμής</label>
                            <input id="inputExpireDateControl" class="form-control" type="date" name="inputExpireDate" value="<?php echo $billData['expire_date'];?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col">
                            <label for="inputPayedDateControl">Πληρώθηκε</label>
                            <input id="inputPayedDateControl" class="form-control" type="date" value="<?php echo $billData['is_paid'];?>" name="inputPayedDate">
                        </div>
                    </div>
                    <input type="hidden" value="<?php echo $id;?>" name="bill-id">
                </form>
            </div>
        </div>
        <!-- /content -->
    </div>

    </div>

    <!-- modals -->

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