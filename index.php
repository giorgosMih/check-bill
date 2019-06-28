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

require_once("includes/database.php");
$sql = '
SELECT
TRUNCATE(SUM(b.amount),2) AS bill_total, t.description AS "desc" 
FROM bill b,bill_type t 
WHERE b.uid='.$_SESSION['uid'].' AND 
b.bill_type = t.id AND 
b.is_paid IS NOT NULL AND 
YEAR(b.is_paid) = YEAR(CURDATE()) 
GROUP BY t.id
';
$yearPayments = $dbConn->query($sql);

$sql = '
SELECT
TRUNCATE(SUM(b.amount),2) AS bill_total, t.description AS "desc" 
FROM bill b,bill_type t 
WHERE b.uid='.$_SESSION['uid'].' AND 
b.bill_type = t.id AND 
b.is_paid IS NOT NULL AND 
YEAR(b.is_paid) = YEAR(CURDATE()) AND
MONTH(b.is_paid) = MONTH(CURDATE())
GROUP BY t.id
';
$currentMonthPayments = $dbConn->query($sql);

$sql='
SELECT t.description, b.expire_date, b.amount 
FROM bill b, bill_type t 
WHERE b.is_paid IS NULL AND 
b.bill_type = t.id AND 
b.uid = '.$_SESSION['uid'].' 
ORDER BY b.expire_date, b.amount
';
$unpaidBills = $dbConn->query($sql);

$sql='
SELECT b.amount, b.till_next_bill, t.description
FROM bill b, bill_type t, 
(
    SELECT value FROM parametrika WHERE param=\'notify_next_bill\'
) param
WHERE b.uid = '.$_SESSION['uid'].' AND b.bill_type = t.id AND
IF( 
    param.value IS NULL, 
    CURDATE() <= b.till_next_bill, 
    CURDATE() BETWEEN ADDDATE(b.till_next_bill, INTERVAL -param.value DAY) AND b.till_next_bill
)
ORDER BY b.till_next_bill
';
$nextBills = $dbConn->query($sql);
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
        .main-content{
            height: calc(100% - 72px)!important;
        }
    </style>
    <script>
        $(document).ready(function() {
            var ctx = $('#myChart');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [
                            <?php 
                                while($row = $yearPayments->fetch_assoc()){
                                    echo $row['bill_total'].',';
                                }
                            ?>
                        ],
                        backgroundColor: [
                            <?php
                                $yearPayments->data_seek(0);
                                while($row = $yearPayments->fetch_assoc()){
                                    echo '"rgb('.rand (0, 255).','.rand (0, 255).','.rand (0, 255).')",';
                                }
                            ?>
                        ]
                    }],

                    labels: [
                        <?php 
                            $yearPayments->data_seek(0);
                            while($row = $yearPayments->fetch_assoc()){
                                echo '"'.$row['desc'].'",';
                            }
                        ?>
                    ]
                },

                options: {
                    responsive: true,
                    legend: {
                        position: 'bottom'
                    },
                    title: {
                        display: true,
                        text: 'Πληρωμές έτους <?php
                            $sql = 'SELECT YEAR(CURDATE()) as cur_year';
                            echo ($dbConn->query($sql)->fetch_assoc())['cur_year'];
                        ?>'
                    }
                }
            });

            var ctx_monthPayments = $('#chartMonthPayments');
            new Chart(ctx_monthPayments, {
                type: 'bar',
				data: {
					datasets: [
						{
							data: [
                                <?php 
                                while($row = $currentMonthPayments->fetch_assoc()){
                                    echo $row['bill_total'].',';
                                }
                                ?>
                            ],
							backgroundColor: [
                                <?php
                                $currentMonthPayments->data_seek(0);
                                while($row = $currentMonthPayments->fetch_assoc()){
                                    echo '"rgb('.rand (0, 255).','.rand (0, 255).','.rand (0, 255).')",';
                                }
                                ?>
							]
						}
					],

					labels: [
						<?php 
                            $currentMonthPayments->data_seek(0);
                            while($row = $currentMonthPayments->fetch_assoc()){
                                echo '"'.$row['desc'].'",';
                            }
                        ?>
					]
				},
				
				options: {
					responsive: true,
					legend: {
						display: false
					},
					title: {
						display: true,
						text: 'Πληρωμές Τρέχοντος Μήνα'
					},
					scales: {
						yAxes: [{
							ticks: {
								beginAtZero: true
							}
						}]
					}
				}
            });
        });
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
                    <li class="nav-item active">
                        <a class="nav-link" href="#">Αρχική <span class="sr-only">(current)</span></a>
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

        <br/>
        <br/>
        <br/>

        <!-- main content -->
        <div class="main-content">
            <div class="row p-0 m-0">
                <div class="chart-container m-0 p-0 col-6">
                    <canvas id="myChart">Your browser does not support the canvas element.</canvas>
                </div>

                <div class="chart-container m-0 p-0 col-6">
                    <canvas id="chartMonthPayments">Your browser does not support the canvas element.</canvas>
                </div>
            </div>

            <?php
            if($unpaidBills->num_rows > 0 || $nextBills->num_rows > 0){
                echo '<hr />
                <div class="row m-0 p-0">
                    <div class="col-6">
                        <h3 class="text-danger text-center m-4">Υπενθύμιση Απλήρωτων Λογαριασμών</h3>
                    </div>
                    <div class="col-6">
                        <h3 class="text-success text-center m-4">Υπενθύμιση Επόμενου Λογαριασμού</h3>
                    </div>
                </div>
                <div class="row m-0 p-0">
                    <div class="col-6">
                ';
                if($unpaidBills->num_rows > 0 ){
                    while($row = $unpaidBills->fetch_assoc()){
                        $tokens = explode("-", $row['expire_date']);
                        if(sizeof($tokens) == 3){
                            $fixedDate = $tokens[2]."-".$tokens[1]."-".$tokens[0];
                        }
                        else{
                            $fixedDate = "-";
                        }
                        echo '
                        <div class="boxed-container m-4 p-2">
                            <div class="row">
                                <div class="col font-italic h6">Λογαριασμός</div>
                                <div class="col font-italic h6">Λήξη Πληρωμής</div>
                                <div class="col font-italic h6">Ποσό</div>
                            </div>
                            <div class="row">
                                <div class="col">'.$row['description'].'</div>
                                <div class="col">'.$fixedDate.'</div>
                                <div class="col">'.$row['amount'].'</div>
                            </div>
                        </div>
                        ';
                    }
                }
                else{
                    echo '<h4 class="text-center font-italic">-</h4>';
                }

                echo '
                </div>
                <div class="col-6">
                ';

                if($nextBills->num_rows > 0){
                    while($row = $nextBills->fetch_assoc()){
                        $tokens = explode("-", $row['till_next_bill']);
                        if(sizeof($tokens) == 3){
                            $fixedDate = $tokens[2]."-".$tokens[1]."-".$tokens[0];
                        }
                        else{
                            $fixedDate = "-";
                        }
                        echo '
                        <div class="boxed-container m-4 p-2">
                            <div class="row">
                                <div class="col font-italic h6">Λογαριασμός</div>
                                <div class="col font-italic h6">Πιθανή Ημ/νία</div>
                            </div>
                            <div class="row">
                                <div class="col">'.$row['description'].'</div>
                                <div class="col">'.$fixedDate.'</div>
                            </div>
                        </div>
                        ';
                    }
                }
                else{
                    echo '<h4 class="text-center font-italic">-</h4>';
                }

                echo '
                </div>
                ';
            }
            ?>
        </div>

    </div>

</body>

</html>
<?php
$dbConn->close();
?>