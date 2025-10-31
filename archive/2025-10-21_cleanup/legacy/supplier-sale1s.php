<?php
include("assets/functions/supplier-config.php");

if (!isset($_GET["supplierID"])) {
  header("Location: https://www.vapeshed.co.nz");
  die();
} else {
  $supplier = getSupplierInformation($_GET["supplierID"]);

  if (is_null($supplier)) {
    header("Location: https://www.vapeshed.co.nz");
    die();
  }
}
include("assets/template/html-header.php");
include("assets/template/supplier-header.php");
?>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
  <div class="app-body">
    <?php include("assets/template/supplier-sidemenu.php") ?>
    <main class="main">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item active">Supplier Sales Data</li>
      </ol>
      <div class="container-fluid">
        <div class="animated fadeIn">
          <div class="col">
            <div class="card">
              <div class="card-header">
                <h4 class="card-title mb-0">"<?php echo $supplier->name; ?>" Recent Sales Data
              </div>
              <div class="card-body transfer-data">
    
                <div class="row">
              
                <div class="col-sm-5" style="padding:0;display:none" id="searchFilters">
                     <div style="float:left" >
                          
                       <!--    <input type="checkbox" onchange="hideNotSold30Days();" id="hideNotSold30Days"> Hide Products Not Sold Last 30 Days<br>
                          <input type="checkbox" onchange="showProductsInStockButNotSoldLast30Days();" id="showProductsInStockButNotSoldLast30Days"> Show Products In Stock But None Sold Last 30 Days<br>
                           --><input type="checkbox" onchange="showProductsWillRunOutOfStockSixWeeks();" id="showProductsWillRunOutOfStockSixWeeks"> <label for="showProductsWillRunOutOfStockSixWeeks">Show Products That will run out of stock in less than 6 weeks</label>
                        <br><a onclick="createCSV();" href="javascript:void(0)">Export as CSV</a>
                     </div>
                     
                  </div>
                <p id="loading" style=" font-size: 40px; text-align: center; margin: auto; ">Grabbing Data...Please Wait<br><img style="height:80px;margin-top:30px;" src="assets/img/loader.gif"></p>
               

                  <table class="table table-responsive-sm table-bordered table-striped table-sm " id="productTable" style="display:none;font-size:12px;">
                    <thead style="cursor:pointer;">
                      <tr>
                        <th>Name <img src="/assets/img/arrows.png" style=" height: 12px; "></th>
                        <th>Qty In Stock <img src="/assets/img/arrows.png" style=" height: 12px; "></th>
                        <th>Days Will Last <img src="/assets/img/arrows.png" style=" height: 12px; "></th>
                        <th>Sold Last 30 Days <img src="/assets/img/arrows.png" style=" height: 12px; "></th>
                        <th>Sold Last 90 Days <img src="/assets/img/arrows.png" style=" height: 12px; "></th>
                        <th>Monthly Average <img src="/assets/img/arrows.png" style=" height: 12px; "></th>
                        <th>Sold Since First Sale <img src="/assets/img/arrows.png" style=" height: 12px; "></th>
                      </tr>
                    </thead>
                    <tbody id="productSearchBody">
                    
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
    </main>
    <?php include("assets/template/personalisation-menu.php") ?>
  </div>
  <?php include("assets/template/html-footer.php") ?>
  <?php include("assets/template/footer.php") ?>

  <script>

function showProductsWillRunOutOfStockSixWeeks(){

var isChecked = document.getElementById('showProductsWillRunOutOfStockSixWeeks').checked;

    $('#productSearchBody tr').each(function(){

      if (isChecked){

        var soldLast30Days = parseInt($(this.children[3]).html());
        if (soldLast30Days > 0){
        var sixWeeks = soldLast30Days / 2;
        soldLast30Days = soldLast30Days + sixWeeks;
        }
        var qtyInStock = parseInt($(this.children[1]).html());

        var recommendedOrderQty = soldLast30Days - qtyInStock;

        if (recommendedOrderQty > 0){
          var rounded = Math.round(recommendedOrderQty / 10) * 10
          $(this).find("input[type=number]").val(rounded);
        }else{
          $(this).find("input[type=number]").val(0);
        }

        if (qtyInStock <= soldLast30Days && soldLast30Days > 0){
          $(this).css("display","table-row");
        }else{
          $(this).css("display","none");
        }

      }else{
        $(this).css("display","table-row");
      }
    });



}

function showProductsInStockButNotSoldLast30Days(){

var isChecked = document.getElementById('showProductsInStockButNotSoldLast30Days').checked;

$('#productSearchBody tr').each(function(){

  if (isChecked){

    var soldLast30Days = parseInt($(this.children[5]).html());
    var qtyInStock = parseInt($(this.children[2]).html());

    if (qtyInStock > 0 && soldLast30Days <= 0){
      $(this).css("display","table-row");
    }else{
      $(this).css("display","none");
    }

  }else{
    $(this).css("display","table-row");
  }
});


}

function hideNotSold30Days(){

var isChecked = document.getElementById('hideNotSold30Days').checked;

$('#productSearchBody tr').each(function(){

  if (isChecked){

    var soldLast30Days = parseInt($(this.children[5]).html());

    if (soldLast30Days <= 0){
      $(this).css("display","none");
    }

  }else{
    $(this).css("display","table-row");
  }
});
}


    function sortTable(table, col, reverse) {
    var tb = table.tBodies[0], // use `<tbody>` to ignore `<thead>` and `<tfoot>` rows
        tr = Array.prototype.slice.call(tb.rows, 0), // put rows into array
        i;
    reverse = -((+reverse) || -1);
    tr = tr.sort(function (a, b) { // sort rows
        return reverse // `-1 *` if want opposite order
            * (a.cells[col].textContent.trim() // using `.textContent.trim()` for test
                .localeCompare(b.cells[col].textContent.trim(), undefined, {numeric: true})
               );
    });
    for(i = 0; i < tr.length; ++i) tb.appendChild(tr[i]); // append each row in order
}

function makeSortable(table) {
    var th = table.tHead, i;
    th && (th = th.rows[0]) && (th = th.cells);
    if (th) i = th.length;
    else return; // if no `<thead>` then do nothing
    while (--i >= 0) (function (i) {
        var dir = 1;
        th[i].addEventListener('click', function () {sortTable(table, i, (dir = 1 - dir))});
    }(i));
}

function makeAllSortable(parent) {
    parent = parent || document.body;
    var t = parent.getElementsByTagName('table'), i = t.length;
    while (--i >= 0) makeSortable(t[i]);
}



  
  
  $.post("assets/functions/ajax.php?method=getSupplierProducts", { outletID: "combined", supplierID: "<?php echo $_GET["supplierID"];?>" }, function(data, status){

    var products = JSON.parse(data);

    $('#productTable tbody').empty();

    for (var i = 0; i < products.length; i++){

      var html = "";       

      html = "<tr></td><td>"+products[i].name;

      if (products[i].onOrder != false){
        html += "<br><span style=' color: red; font-size: 10px; '>[Currently "+products[i].onOrder[0].order_qty+" Ordered In Purchase Order #"+products[i].onOrder[0].purchase_order_id+"]</span>";
      }

      var amountSold = products[i].soldLastMonth;

      if (products[i].average > products[i].soldLastMonth){
        var amountSold = products[i].average;
      }

      var soldPerDay = amountSold / 30;
      var daysLast = Math.round(Math.floor(products[i].inventory_level / soldPerDay));

      if (isNaN(daysLast) || !isFinite(daysLast)){
        daysLast = 0;
      }

      daysLast = daysLast + " Days";

      if (amountSold == 0){
        daysLast = "0 Days";
      }

      html +="</td><td>"+products[i].inventory_level+"</td><td>"+daysLast+"</td><td>"+products[i].soldLastMonth+"</td><td>"+products[i].soldLast90Days+"</td><td>"+products[i].average+"</td><td>"+products[i].soldSinceFirstSeen.totalSold+" in "+products[i].soldSinceFirstSeen.dayDifference+" Days ("+products[i].soldSinceFirstSeen.perDayAverage+" Per Day)</td></tr>";

      $('#productTable tbody').append(html);
 
    }
    $('#loading').hide();
    makeAllSortable();
    $('#productTable,#searchFilters').show();
    
});

function createCSV(){

const rows = [["Product", "Qty In Stock","Days Will Last", "Sold Last 30 Days","Sold Last 60 Days","Sold Last 90 Days","Sold Since First Sale"]];

$('#productTable tbody tr').each(function(){
  if ($(this).css("display") != "none"){
    var data = [];
    data[0] = $(this.children[0]).html(); 
    data[1] = $(this.children[1]).html();
    data[2] = $(this.children[2]).html();
    data[3] = $(this.children[3]).html();
    data[4] = $(this.children[4]).html();
    data[5] = $(this.children[5]).html();
    data[6] = $(this.children[6]).html();
    rows.push(data);
  }
});



let csvContent = "data:text/csv;charset=utf-8,";

rows.forEach(function(rowArray) {
  let row = rowArray.join(",");
  csvContent += row + "\r\n";
});
exportToCsv("csv-export.csv",rows);
}

function exportToCsv(filename, rows) {
var processRow = function (row) {
  var finalVal = '';
  for (var j = 0; j < row.length; j++) {
      var innerValue = row[j] === null ? '' : row[j].toString();
      if (row[j] instanceof Date) {
          innerValue = row[j].toLocaleString();
      };
      var result = innerValue.replace(/"/g, '""');
      if (result.search(/("|,|\n)/g) >= 0)
          result = '"' + result + '"';
      if (j > 0)
          finalVal += ',';
      finalVal += result;
  }
  return finalVal + '\n';
};

var csvFile = '';
for (var i = 0; i < rows.length; i++) {
  csvFile += processRow(rows[i]);
}

var blob = new Blob([csvFile], { type: 'text/csv;charset=utf-8;' });
if (navigator.msSaveBlob) { // IE 10+
  navigator.msSaveBlob(blob, filename);
} else {
  var link = document.createElement("a");
  if (link.download !== undefined) { // feature detection
      // Browsers that support HTML5 download attribute
      var url = URL.createObjectURL(blob);
      link.setAttribute("href", url);
      link.setAttribute("download", filename);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
  }
}
}

  
  </script>