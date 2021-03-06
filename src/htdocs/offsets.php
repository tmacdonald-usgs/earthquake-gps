<?php

include_once '../conf/config.inc.php'; // app config
include_once '../lib/_functions.inc.php'; // app functions
include_once '../lib/classes/Db.class.php'; // db connector, queries

$network = safeParam('network', 'SFBayArea');

if (!isset($TEMPLATE)) {
  $TITLE = "$network Network";
  $NAVIGATION = true;
  $HEAD = '<link rel="stylesheet" href="../css/offsets.css" />';
  $FOOT = '<script src="../js/sortAndTabifyTable.js"></script>';
  $CONTACT = 'jsvarc';

  include 'template.inc.php';
}

$db = new Db;

// Db query result: offsets for selected network
$rsOffsets = $db->queryOffsets($network);

$datatypes = [
  'nafixed' => 'NA-fixed',
  'itrf2008' => 'ITRF2008',
  'filtered' => 'Filtered'
];

// Create html for tables
$html = '';
$tableHeader = '<table class="sortable">
  <tr class="no-sort">
    <th class="sort-default">Station</th>
    <th>Date</th>
    <th>Decimal Date</th>
    <th>N Offset</th>
    <th>N Uncertainty</th>
    <th>E Offset</th>
    <th>E Uncertainty</th>
    <th>U Offset</th>
    <th>U Uncertainty</th>
    <th>Type</th>
  </tr>';
$tableBody = [];
$tableFooter = '</table>';

while ($row = $rsOffsets->fetch(PDO::FETCH_OBJ)) {
  // sizes and uncertainties are comma-separated in this format: $datatype/$component:$value
  $sizeValues = [];
  $sizes = explode(',', $row->size);
  foreach ($sizes as $size) {
    // separate out constituent parts
    preg_match('@(\w+)/(E|N|U):([-\d.]+)@', $size, $matches);
    $datatype = $matches[1];
    $component = $matches[2];
    $value = $matches[3];

    $sizeValues[$datatype][$component] = $value;
  }

  $uncertaintyValues = [];
  $uncertainties = explode(',', $row->uncertainty);
  foreach ($uncertainties as $uncertainty) {
    // separate out constituent parts
    preg_match('@(\w+)/(E|N|U):([-\d.]+)@', $uncertainty, $matches);
    $datatype = $matches[1];
    $component = $matches[2];
    $value = $matches[3];

    $uncertaintyValues[$datatype][$component] = $value;
  }

  foreach($datatypes as $datatype=>$name) {
    if ($sizeValues[$datatype] && $uncertaintyValues[$datatype]) { // only create table if there's data
      $tableBody[$datatype] .= sprintf('<tr>
          <td>%s</td>
          <td>%s</td>
          <td>%s</td>
          <td>%s</td>
          <td>%s</td>
          <td>%s</td>
          <td>%s</td>
          <td>%s</td>
          <td>%s</td>
          <td>%s</td>
        </tr>',
        $row->station,
        $row->date,
        $row->decdate,
        $sizeValues[$datatype]['N'],
        $uncertaintyValues[$datatype]['N'],
        $sizeValues[$datatype]['E'],
        $uncertaintyValues[$datatype]['E'],
        $sizeValues[$datatype]['U'],
        $uncertaintyValues[$datatype]['U'],
        $row->offsettype
      );
    }
  }
}

foreach ($datatypes as $datatype => $name) {
  if ($tableBody[$datatype]) {
    $html .= sprintf('<section class="panel" data-title="%s">
        <header>
          <h3>%s</h3>
        </header>
        %s
        %s
        %s
      </section>',
      $name,
      $name,
      $tableHeader,
      $tableBody[$datatype],
      $tableFooter
    );
  }
}

$backLink = sprintf('%s/%s',
  $MOUNT_PATH,
  $network
);

?>

<h2>Offsets</h2>

<div class="tablist">
  <?php print $html; ?>
</div>

<p class="back">&laquo;
  <a href="<?php print $backLink; ?>">Back to <?php print $network; ?> network</a>
</p>
