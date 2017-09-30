<?php // sales_report_02.php 

// get user id
session_start();
$userid = $_SESSION["userid"]; 

// get script
$script = substr($_SERVER['PHP_SELF'],1,48);
$script = basename(__FILE__);

// open database ...
include 'database_connect.php';

// check authorization
include'fgc_lis_functions.php';
$authorized = check_authorization ($userid, $script, $authorized);

if (!$authorized) {
  header("Location: access_check.html");
  exit;
} 

// open database
include 'db_connection.php';

// Date	Customer	SKU Alias	Price	Discount	Rewards	Net	Maker	Model	CPU Type	CPU Speed	Memory Size	HDD Size
//echo '<pre>';

  echo '<h3>FGC-LIS: Sales Report</h3>';

//  echo '<h3>From  ' .  $date('Y-m-d');

  echo "<h3>From: " . $_POST[dateFrom] . str_repeat('&nbsp;', 10) . "To: " . $_POST[dateTo];
  echo "<br />"."<br />";


       echo '<table border="" cellpadding="4" cellspacing="0">';
       echo '<tr>
               <th>Invoice</th>  
               <th>Date of Sales</th>        
               <th>Customer</th> 
               <th>SKU</th> 
               <th>Price</th> 
               <th>Discount</th> 
               <th>Rewards</th> 
               <th>Net Amount</th> 
               <th>Maker</th> 
               <th>Model</th> 
               <th>CPU Type</th> 
               <th>CPU Model</th>
               <th>CPU Speed (GHz)</th> 
               <th>Memory Size (GB)</th> 
               <th>HDD Size (GB)</th>
               <th>Pricing Date</th>
               <th>Days Unsold</th> 
             </tr>';

$sortby = $_POST['sortby'];

switch ($sortby) {

  case 'price':
    $query = "select * from sales 
               inner join laptop on sales.sku = laptop.sku 
               where laptop.sdate between '$_POST[dateFrom]' and '$_POST[dateTo]'
	       order by laptop.price, laptop.maker, laptop.model, invnum";
      break;

  case 'maker':
    $query = "select * from sales 
               inner join laptop on sales.sku = laptop.sku 
               where laptop.sdate between '$_POST[dateFrom]' and '$_POST[dateTo]'
	       order by laptop.maker, laptop.model, laptop.cputype, invnum";
      break;

  case 'cputype':
    $query = "select * from sales 
               inner join laptop on sales.sku = laptop.sku 
               where laptop.sdate between '$_POST[dateFrom]' and '$_POST[dateTo]'
	       order by laptop.cputype, laptop.maker, laptop.model, invnum";
      break;

  case 'cpuspeed':
    $query = "select * from sales 
               inner join laptop on sales.sku = laptop.sku 
               where laptop.sdate between '$_POST[dateFrom]' and '$_POST[dateTo]'
	       order by laptop.cpuspeed, laptop.maker, laptop.model, invnum";
      break;

  case 'hddsize':
    $query = "select * from sales 
               inner join laptop on sales.sku = laptop.sku 
               where laptop.sdate between '$_POST[dateFrom]' and '$_POST[dateTo]'
	       order by laptop.hdsize, laptop.maker, laptop.model, invnum";
      break;

  case 'sku':
    $query = "select * from sales 
               inner join laptop on sales.sku = laptop.sku 
               where laptop.sdate between '$_POST[dateFrom]' and '$_POST[dateTo]'
	       order by laptop.sku, invnum";
      break;

  default:
    $query = "select * from sales 
               inner join laptop on sales.sku = laptop.sku 
               where laptop.sdate between '$_POST[dateFrom]' and '$_POST[dateTo]'
	       order by laptop.sdate, invnum";    
}

//$query = "select * from sales 
//          inner join laptop on sales.sku = laptop.sku 
//          where laptop.sdate between '$_POST[dateFrom]' and '$_POST[dateTo]'
//	  order by laptop.sdate, invnum";

$result = mysqli_query($db_connection, $query)
          or die(mysqli_error($db_connection));
if (mysqli_num_rows($result) > 0) {
  while ($data = mysqli_fetch_array($result)){
    // get and format net amount
    //$netAmount = $data['price'] - $data['discount'] - $data['rewards'];
    $netAmount = $data['price'] - $data['discount']  - $data['discamt'] - $data['rewards'];
    $netAmount = number_format($netAmount, 2, '.', '');
    $discount  = $data['discount'] + $data['discamt'];      
    $discount  = number_format($discount, 2, '.', '');    
    // get number of days unsold
    $pricingDate = new DateTime($data[pdate]);
    $salesDate   = new DateTime($data[sdate]);
    $interval = $salesDate->diff($pricingDate);
    $daysUnsold = $interval->days;

    echo "<tr>
            <td align='center'><a href='sales_receipt_display_02.php?invoice=$data[invnum]' style='text-decoration: none'>$data[invnum]</a></td>             
            <td>$data[sdate]</td>                 
            <td>$data[customer]</td>
            <td align='center'><a href='laptop_data_review_drilldown_12.php?skuid=$data[sku]' style='text-decoration: none'>$data[skualias] </a></td>   
            <td align='right'>$data[price]</td>
            <td align='right'>$discount</td>
            <td align='right'>$data[rewards]</td>
            <td align='right'>$netAmount</td>
            <td>$data[maker]</td>
            <td>$data[model]</td>
            <td>$data[cputype]</td>
            <td>$data[cpumodel]</td>
            <td align='center'>$data[cpuspeed]</td>
            <td align='center'>$data[memsize]</td>
            <td align='center'>$data[hdsize]</td>
            <td align='center'>$data[pdate]</td>
            <td align='center'>$daysUnsold</td>
          </tr>";
          $skus++;
          $totalPrice = $totalPrice + $data[price];
          $totalDiscount = $totalDiscount + $discount;
          $totalReward = $totalReward + $data[rewards];
          $totalNetAmount = $totalNetAmount + $netAmount;
     // get sales returns
     if ($_POST[returns] != '')  {
       $query1 = "SELECT * from returns where sku = $data[sku];";
       $result1 = mysqli_query($db_connection, $query1)
          or die(mysqli_error($db_connection));
       if (mysqli_num_rows($result1) > 0) {
         while ($data1 = mysqli_fetch_array($result1)){
           //echo "<br/> include returns" . $data[sku];
           $rprice = number_format($data1[price] * -1, 2, '.', ','); 
           $rdiscount = number_format($data1[discount] * -1, 2, '.', ',');  
           $rrewards = number_format($data1[rewards] * -1, 2, '.', ','); 
           $rnetAmount = $rprice - $rdiscount - $rrewards;
           $rnetAmount = number_format($rnetAmount, 2, '.', ',');            
           echo "<tr style='background-color:orange;'>
            <td align='center'>$data1[invnum]</td> 	
            <td>$data1[sdate]</td>                 
            <td>$data1[customer]</td>
            <td align='center'><a href='laptop_data_review_drilldown_12.php?skuid=$data1[sku]' style='text-decoration: none'>$data[skualias] </a></td>   
            <td align='right'>$rprice</td>
            <td align='right'>$rdiscount</td>
            <td align='right'>$rrewards</td>
            <td align='right'>$rnetAmount</td>
            <td>$data[maker]</td>
            <td>$data[model]</td>
            <td>$data[cputype]</td>
            <td>$data[cpumodel]</td>
            <td align='center'>$data[cpuspeed]</td>
            <td align='center'>$data[memsize]</td>
            <td align='center'>$data[hdsize]</td>
            <td align='center'>$data1[rdate]</td>
            <td align='center'>$daysUnsold</td>
          </tr>";    
          $skus--;
          $totalPrice = $totalPrice - $data[price];
          $totalDiscount = $totalDiscount - $data[discount];
          $totalReward = $totalReward - $data[rewards];
          $totalNetAmount = $totalNetAmount - $netAmount;
           
         } //  while ($data = mysqli_
       } // if (mysqli_num_
     } // if ($_POST[returns] != '')  

  }  // end while

    $totalPrice = number_format($totalPrice, 2, '.', ',');
    $totalDiscount = number_format($totalDiscount, 2, '.', ',');
    $totalReward = number_format($totalReward, 2, '.', ',');
    $totalNetAmount = number_format($totalNetAmount, 2, '.', ',');
    echo $number;
    echo "<tr>
            <td align='center'>Total</td>
            <td> ------> </td>
            <td> ------> </td>
            <td align='center'>$skus</td>
            <td align='right'>$totalPrice</td>
            <td align='right'>$totalDiscount</td>
            <td align='right'>$totalReward</td>
            <td align='right'>$totalNetAmount</td>
            <td align='center'> - </td>
            <td align='center'> - </td>
            <td align='center'> - </td>
            <td align='center'> - </td>
            <td align='center'> - </td>
            <td align='center'> - </td>
            <td align='center'> - </td>
            <td align='center'> - </td>
            <td align='center'> - </td>
          </tr>";

    echo '</table>';
} // endif

echo "
<p><a href='sales_menu_01.php'>Sales Menu </a></p>";

// echo '</pre>';
?>
