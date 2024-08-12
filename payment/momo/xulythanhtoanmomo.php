<?php
header('Content-type: text/html; charset=utf-8');
ob_start();
session_start();

require_once('../../admin/inc/config.php');

$amount = $_POST['amount']; // Lấy giá trị từ form thanh toán MoMo
$product_names = $_POST['product_name'];
$product_prices = $_POST['product_price'];
$product_qtys = $_POST['product_qty'];
$product_ids = $_POST['product_id']; 
function execPostRequest($url, $data)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data))
    );
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    //execute post
    $result = curl_exec($ch);
    //close connection
    curl_close($ch);
    return $result;
}

$endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";

$partnerCode = 'MOMOBKUN20180529';
$accessKey = 'klm05TvNBzhg7h7j';
$secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';

$orderInfo = "Thanh toán qua mã QR MoMo";
$orderId = time() . "";  // Mã giao dịch duy nhất
$redirectUrl = "http://localhost:8080/ecommerceweb/checkout.php";
$ipnUrl = "http://localhost:8080/ecommerceweb/checkout.php";
$extraData = "";

$requestId = time() . "";
$requestType = "captureWallet";
$rawHash = "accessKey=" . $accessKey . "&amount=" . $amount . "&extraData=" . $extraData . "&ipnUrl=" . $ipnUrl . "&orderId=" . $orderId . "&orderInfo=" . $orderInfo . "&partnerCode=" . $partnerCode . "&redirectUrl=" . $redirectUrl . "&requestId=" . $requestId . "&requestType=" . $requestType;

$signature = hash_hmac("sha256", $rawHash, $secretKey);

$data = array('partnerCode' => $partnerCode,
    'partnerName' => "Test",
    "storeId" => "MomoTestStore",
    'requestId' => $requestId,
    'amount' => $amount,
    'orderId' => $orderId,
    'orderInfo' => $orderInfo,
    'redirectUrl' => $redirectUrl,
    'ipnUrl' => $ipnUrl,
    'lang' => 'vi',
    'extraData' => $extraData,
    'requestType' => $requestType,
    'signature' => $signature);

$result = execPostRequest($endpoint, json_encode($data));
$jsonResult = json_decode($result, true);  // decode json

// Lưu thông tin thanh toán vào bảng tbl_payment
$statement = $pdo->prepare("INSERT INTO tbl_payment (
						customer_id,
						customer_name,
						customer_email,
						payment_date,
						txnid, 
						paid_amount,
						card_number,
                        card_cvv,
                        card_month,
                        card_year,
                        bank_transaction_info,
                        payment_method,
						payment_status,
						shipping_status,
						payment_id
						) 
						VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

$sql = $statement->execute(array(
	$_SESSION['customer']['cust_id'],         // ID khách hàng
	$_SESSION['customer']['cust_name'],       // Tên khách hàng
	$_SESSION['customer']['cust_email'],      // Email khách hàng
	date('Y-m-d H:i:s'),                      // Ngày thanh toán
	$orderId,                                 // Mã giao dịch (orderId từ MoMo)
	$amount,                                  // Số tiền đã thanh toán
	'',                                       // Số thẻ (để trống vì MoMo không yêu cầu)
	'',                                       // CVV thẻ (để trống vì MoMo không yêu cầu)
	'',                                       // Tháng hết hạn thẻ (để trống vì MoMo không yêu cầu)
	'',                                       // Năm hết hạn thẻ (để trống vì MoMo không yêu cầu)
	$extraData,                               // Thông tin giao dịch ngân hàng (extraData từ MoMo, nếu có)
	'MoMo',                                   // Phương thức thanh toán (MoMo)
	'Pending',                                // Trạng thái thanh toán (bắt đầu là "Pending")
	'Pending',                                // Trạng thái vận chuyển (bắt đầu là "Pending")
	$orderId                                  // Mã thanh toán (thường là mã giao dịch)
));

// Lưu thông tin đơn hàng vào bảng tbl_order
$dbhost = 'localhost';
$dbname = 'ecommerceweb';
$dbuser = 'root';
$dbpass = '';

$conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// for ($i = 0; $i < count($product_names); $i++) {
//     $product_name = $product_names[$i];
//     $product_qty = $product_qtys[$i];
//     $product_price = $product_prices[$i];

//     $sql = "INSERT INTO tbl_order (product_name, quantity, unit_price, payment_id) VALUES (?, ?, ?, ?)";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("sidi", $product_name, $product_qty, $product_price, $orderId);
//     $stmt->execute();
// }

for ($i = 0; $i < count($product_names); $i++) {
    $product_id = $product_ids[$i]; // Retrieve the product_id for each item
    $product_name = $product_names[$i];
    $product_qty = $product_qtys[$i];
    $product_price = $product_prices[$i];

    $sql = "INSERT INTO tbl_order (product_id, product_name, quantity, unit_price, payment_id) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isidi", $product_id, $product_name, $product_qty, $product_price, $orderId); // Bind the product_id as well
    $stmt->execute();
}

$stmt->close();
$conn->close();

// Chuyển hướng người dùng đến trang thanh toán của MoMo
header('Location: ' . $jsonResult['payUrl']);
?>
