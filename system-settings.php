<?php
session_name('checkBill_SESSID');
session_start();
require_once("includes/settings.php");

if (!isset($_SESSION['uid'])) {
    header('Location: ' . $app_root . '/login.php');
    die();
}

if ($_SESSION['user_type'] == 1) {
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
    if (isset($_POST['btnAddBillType'])) {
        require_once("includes/database.php");
        $months = filter_var($_POST['inputMonths'], FILTER_SANITIZE_NUMBER_INT);
        $desc = filter_var($_POST['inputBillName'], FILTER_SANITIZE_STRING);

        $sql = '
        INSERT INTO bill_type(description,till_next_bill)
        VALUES(?,?)
        ';

        $stmt = $dbConn->prepare($sql);
        $stmt->bind_param('si', $desc, $months);
        $stmt->execute();

        if ($stmt->error) {
            if ($stmt->errno == 1062) {
                $_SESSION['message_code'] = -2;
                $_SESSION['message_data'] = $desc;
            } else {
                $_SESSION['message_code'] = -1;
                $_SESSION['message_data'] = 'DB_ERROR> (' . $stmt->errno . '): ' . $stmt->error;
            }
        } else {
            $_SESSION['message_code'] = 1;
        }

        $stmt->close();
        $dbConn->close();

        header('Location: ' . $_SERVER['PHP_SELF']);
        die();
    } else if (isset($_POST['btnUpdateBills'])) {
        require_once("includes/database.php");

        $size = filter_var($_POST['bills-size'], FILTER_SANITIZE_NUMBER_INT);
        $sql = '
        UPDATE bill_type 
        SET description=?, till_next_bill=? 
        WHERE id=?
        ';
        for ($index = 1; $index <= $size; $index++) {
            $id = filter_var($_POST['bill-id-' . $index], FILTER_SANITIZE_NUMBER_INT);
            $desc = filter_var($_POST['inputDesc-' . $index], FILTER_SANITIZE_STRING);
            $months = filter_var($_POST['inputMonths-' . $index], FILTER_SANITIZE_NUMBER_INT);

            $stmt = $dbConn->prepare($sql);
            $stmt->bind_param('sii', $desc, $months, $id);
            $stmt->execute();

            if ($stmt->error) {
                $_SESSION['message_code'] = -1;
                $_SESSION['message_data'] = 'DB_ERROR> (' . $stmt->errno . '): ' . $stmt->error;

                $stmt->close();
                $dbConn->close();
                header('Location: ' . $_SERVER['PHP_SELF']);
                die();
            }

            $stmt->close();
        }

        $_SESSION['message_code'] = 2;
        $dbConn->close();

        header('Location: ' . $_SERVER['PHP_SELF']);
        die();
    } else if (isset($_POST['btnUpdateParamNextBill'])) {
        require_once("includes/database.php");
        $value = (empty($_POST['paramNextBill'])) ? 'null' : filter_var($_POST['paramNextBill'], FILTER_SANITIZE_NUMBER_INT);

        $sql = 'UPDATE parametrika SET value=' . $value . ' WHERE param="notify_next_bill"';
        $dbConn->query($sql);
        if ($dbConn->error) {
            $_SESSION['message_code'] = -1;
            $_SESSION['message_data'] = 'DB_ERROR> (' . $dbConn->errno . '): ' . $dbConn->error;
        } else {
            $_SESSION['message_code'] = 4;
        }

        $dbConn->close();

        header('Location: ' . $_SERVER['PHP_SELF']);
        die();
    } else if(isset($_POST['btnUpdateParamAlertVisibleTime'])){
        require_once("includes/database.php");
        $value = filter_var($_POST['paramAlertVisibleTime'], FILTER_SANITIZE_NUMBER_INT);

        $sql = 'UPDATE parametrika SET value=' . $value . ' WHERE param="alert_visible_time"';
        $dbConn->query($sql);
        if ($dbConn->error) {
            $_SESSION['message_code'] = -1;
            $_SESSION['message_data'] = 'DB_ERROR> (' . $dbConn->errno . '): ' . $dbConn->error;
        } else {
            $_SESSION['message_code'] = 5;
        }

        $dbConn->close();

        header('Location: ' . $_SERVER['PHP_SELF']);
        die();
    }
}

if (isset($_GET['del-id']) && !empty($_GET['del-id'])) {
    require_once("includes/database.php");
    $id = filter_var($_GET['del-id'], FILTER_SANITIZE_NUMBER_INT);
    $sql = 'DELETE FROM bill_type WHERE id=?';
    $stmt = $dbConn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows != 1) {
        $_SESSION['message_code'] = -1;
        $_SESSION['message_data'] = 'DB_ERROR> (' . $stmt->errno . '): ' . $stmt->error;
    } else
        $_SESSION['message_code'] = 3;

    $stmt->close();
    $dbConn->close();

    header('Location: ' . $_SERVER['PHP_SELF']);
    die();
}

require_once("includes/database.php");
/**
 * Code to display message after any update.
 * -2: bill_type exists
 * -1: database error.
 * 0: no message.
 * 1: bill_type added successfully.
 * 2: bill_type updated successfully.
 * 3: bill_type deleted successfully.
 * 4: param nofity next bill updated successfully.
 * 5: param alert visible time updated successfully.
 */
$messageCode = 0;
$messageData = null;
if (isset($_SESSION['message_code'])) {
    $messageCode = $_SESSION['message_code'];
    $messageData = (isset($_SESSION['message_data']) ? $_SESSION['message_data'] : null);
    unset($_SESSION['message_code']);
    unset($_SESSION['message_data']);
}

$sql = 'SELECT value FROM parametrika WHERE param="alert_visible_time"';
$alertVisibleTime=$dbConn->query($sql)->fetch_assoc()['value'];
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
    <link rel="stylesheet" href="modules/chart_js/chart.css">
    <script src="modules/chart_js/chart.bundle.js"></script>
    <style>
        .main-content {
            height: calc(100% - 71px) !important;
        }
    </style>
    <script>
        $(document).ready(function() {
            var alert = $('.alert');
            if (alert) {
                $(alert).delay(<?php echo $alertVisibleTime;?>).fadeOut(800);
            }

            $('#modalDeleteBillType').on('hide.bs.modal', function() {
                $('#modalDeleteBillType').removeData('id');
            });
        });

        function showDeleteBillTypeModal(e, id) {
            $('#modalDeleteBillType').data('id', id);
            $('#modalDeleteBillType').modal('show');
            e.stopPropagation();
            e.preventDefault();
        }

        function deleteBillType() {
            var id = $('#modalDeleteBillType').data('id');
            window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>?del-id=' + id;
        }
    </script>
</head>

<body>

    <div class="container-fluid">

        <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
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

        <br />
        <br />
        <br />

        <!-- main content -->
        <div class="main-content">

            <?php
            if ($messageCode != 0) {
                if ($messageCode > 0) {
                    echo '<div class="alert alert-success alert-dismissible fade show mr-3 ml-3" role="alert">';
                    if ($messageCode == 1) {
                        echo '<strong>Επιτυχία!</strong> Η νέα κατηγορία λογαριασμού αποθηκεύτηκε με επιτυχία.';
                    } else if ($messageCode == 2) {
                        echo '<strong>Επιτυχία!</strong> Οι κατηγορίες λογαριασμών ενημερώθηκαν με επιτυχία.';
                    } else if ($messageCode == 3) {
                        echo '<strong>Επιτυχία!</strong> Η κατηγορία λογαριασμού διαγράφηκε με επιτυχία.';
                    } else if ($messageCode == 4) {
                        echo '<strong>Επιτυχία!</strong> Η παράμετρος "Υπενθύμιση Επόμενου Λογαριασμού" ενημερώθηκε με επιτυχία.';
                    } else if ($messageCode == 5) {
                        echo '<strong>Επιτυχία!</strong> Η παράμετρος "Διάρκεια Εμφάνισης Μυνημάτων" ενημερώθηκε με επιτυχία.';
                    }
                } else {
                    echo '<div class="alert alert-danger alert-dismissible fade show mr-3 ml-3" role="alert">';
                    if ($messageCode == -1)
                        echo '<strong>Σφάλμα Βάσης Δεδομένων!</strong> ' . $messageData;
                    else if ($messageCode == -2)
                        echo '<strong>Σφάλμα!</strong> Το όνομα λογαριασμού "' . $messageData . '" υπάρχει ήδη. Εισάγετε ένα άλλο "Όνομα Λογαριασμού".';
                }
                ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php
    }
    ?>

        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <h3 class="ml-4">Παράμετροι Λογαριασμών</h3>
            <div class="boxed-container m-4 p-3">
                <button type="button" class="btn btn-success m-0 p-0 pl-1 pr-1" title="Προσθήκη Παραμέτρου" data-toggle="modal" data-target="#modalAddBillType">Προσθήκη</button>
                <hr />
                <?php
                $sql = 'SELECT * FROM bill_type';
                $result = $dbConn->query($sql);
                if ($result) {
                    echo '<div class="form-row">';
                    $index = 1;
                    while ($row = $result->fetch_assoc()) {
                        echo '
                            <div class="form-group col-4 p-2">
                                <label class="w-100">
                                    <div class="form-row">
                                        <button type="button" onclick="showDeleteBillTypeModal(event,' . $row['id'] . ')" class="btn btn-danger m-0 ml-1 mr-1 p-0 pl-2 pr-2" title="Διαγραφή">X</button>
                                        <div class="col">
                                            <input type="text" name="inputDesc-' . $index . '" class="form-control form-control-sm" value="' . $row['description'] . '">
                                        </div>
                                    </div>
                                </label>
                                <div class="form-row p-0 m-0">
                                    <input class="form-control col" type="number" name="inputMonths-' . $index . '" value="' . $row['till_next_bill'] . '" required>
                                    <label class="ml-2 font-italic">(Μήνες μέχρι τον<br />επόμενο λογαριασμό)</label>
                                </div>
                                <input type="hidden" name="bill-id-' . $index . '" value="' . $row['id'] . '">
                            </div>
                            ';
                        $index++;
                    }
                    echo '</div>';
                }
                ?>
                <hr />
                <input type="hidden" name="bills-size" value="<?php echo $result->num_rows; ?>">
                <button type="submit" class="btn btn-primary m-0 p-0 pl-1 pr-1" name="btnUpdateBills" title="Αποθήκευση Αλλαγών">Αποθήκευση</button>
            </div>
        </form>
        <br />
        <div class="row m-0 p-0">
            <div class="col-6">
                <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <h3 class="ml-4">Υπενθύμιση Επόμενου Λογαριασμού</h3>
                    <div class="boxed-container m-4 p-3">
                        <div class="form-row m-0 p-0">
                            <div class="form-group col">
                                <label for="paramNextBillControl">Υπενθύμιση μετά από: (σε ημέρες)<br />(Για εμφάνιση όλων των λογαριασμών, ανεξαρτήτου ημερών, αποθηκεύστε με κενό πεδίο)</label>
                                <input id="paramNextBillControl" name="paramNextBill" class="form-control form-control-sm" type="number" value="<?php
                                    $sql = 'SELECT value FROM parametrika WHERE param="notify_next_bill"';
                                    echo $dbConn->query($sql)->fetch_assoc()['value'];
                                ?>">
                            </div>
                        </div>
                        <hr />
                        <button type="submit" class="btn btn-primary m-0 p-0 pl-1 pr-1" name="btnUpdateParamNextBill" title="Αποθήκευση Αλλαγών">Αποθήκευση</button>
                    </div>
                </form>
            </div>
            <div class="col-6">
                <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <h3 class="ml-4">Διάρκεια Εμφάνισης Μυνημάτων</h3>
                    <div class="boxed-container m-4 p-3">
                        <div class="form-row m-0 p-0">
                            <div class="form-group col">
                                <label for="paramAlertVisibleTimeControl">Διάρκεια σε ms: <br />(το 1 millisecond είναι 1000 δευτερόλεπτα)</label>
                                <input id="paramAlertVisibleTimeControl" name="paramAlertVisibleTime" class="form-control form-control-sm" type="number" required value="<?php
                                    $sql = 'SELECT value FROM parametrika WHERE param="alert_visible_time"';
                                    echo $dbConn->query($sql)->fetch_assoc()['value'];
                                ?>">
                            </div>
                        </div>
                        <hr />
                        <button type="submit" class="btn btn-primary m-0 p-0 pl-1 pr-1" name="btnUpdateParamAlertVisibleTime" title="Αποθήκευση Αλλαγών">Αποθήκευση</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    </div>

    <!-- modals -->

    <!-- modal add bill_type -->
    <div id="modalAddBillType" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="addBillTypeModal" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBillTypeModal">Προσθήκη Κατηγορίας Λογαριασμού</h5>
                    <button class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formAddBillType" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                        <div class="form-row">
                            <div class="form-group col">
                                <label for="inputBillNameControl">Όνομα Λογαριασμού</label>
                                <input id="inputBillNameControl" name="inputBillName" class="form-control" type="text" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="inputMonthsControl">Μήνες μέχρι τον επόμενο λογαριασμό</label>
                            <input id="inputMonthsControl" name="inputMonths" class="form-control" type="number" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success" form="formAddBillType" name="btnAddBillType">Αποθήκευση</button>
                    <button class="btn btn-secondary" data-dismiss="modal">Άκυρο</button>
                </div>
            </div>
        </div>
    </div>
    <!-- /modal add bill_type -->

    <!-- modal delete bill_type -->
    <div id="modalDeleteBillType" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="deleteBillTypeModal" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteBillTypeModal">Διαγραφή Κατηγορίας Λογαριασμού</h5>
                    <button class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Είστε σίγουροι οτι θέλετε να διαγράψετε αυτήν την κατηγορία λογαριασμού;</p>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="deleteBillType()" class="btn btn-danger">Διαγραφή</button>
                    <button class="btn btn-secondary" data-dismiss="modal">Άκυρο</button>
                </div>
            </div>
        </div>
    </div>
    <!-- /modal delete bill_type -->

</body>

</html>
<?php
$dbConn->close();
?>