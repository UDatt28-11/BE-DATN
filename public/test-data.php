<!DOCTYPE html>
<html>
<head>
    <title>Test Create Invoice from Booking</title>
</head>
<body>
    <h1>Kiểm tra dữ liệu</h1>
    <?php
    // Kết nối tới database
    $conn = mysqli_connect("localhost", "root", "", "bookstay");
    
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    
    echo "<h2>Booking Orders:</h2>";
    $result = mysqli_query($conn, "SELECT * FROM booking_orders");
    while ($row = mysqli_fetch_assoc($result)) {
        echo "ID: " . $row['id'] . ", Code: " . $row['order_code'] . "<br>";
    }
    
    echo "<h2>Booking Details:</h2>";
    $result = mysqli_query($conn, "SELECT * FROM booking_details");
    while ($row = mysqli_fetch_assoc($result)) {
        echo "ID: " . $row['id'] . ", Booking Order: " . $row['booking_order_id'] . ", Room: " . $row['room_id'] . "<br>";
    }
    
    echo "<h2>Booking Services:</h2>";
    $result = mysqli_query($conn, "SELECT * FROM booking_services");
    if (mysqli_num_rows($result) == 0) {
        echo "No booking services";
    }
    while ($row = mysqli_fetch_assoc($result)) {
        echo "ID: " . $row['id'] . "<br>";
    }
    
    mysqli_close($conn);
    ?>
</body>
</html>
