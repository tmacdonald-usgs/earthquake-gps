<?php

/**
 * Station view
 * - creates the HTML for station.php
 *
 * @author Scott Haefner <shaefner@usgs.gov>
 */
class StationView {
  private $_model;

  public function __construct (Station $model) {
    $this->_baseDir = $GLOBALS['DATA_DIR'];
    $this->_baseUri = $GLOBALS['MOUNT_PATH'] . '/data';

    $this->_model = $model;
  }

  private function _getBackLink () {
    return sprintf('<p class="back">&laquo; <a href="%s/%s">Back to %s network</a></p>',
      $GLOBALS['MOUNT_PATH'],
      $this->_model->network,
      $this->_model->network
    );
  }

  private function _getData () {
    $html = '<div class="tablist">';
    $datatypes = [
      'nafixed' => 'NA-fixed',
      'itrf2008' => 'ITRF2008',
      'filtered' => 'Filtered'
    ];

    foreach ($datatypes as $datatype => $name) {
      $baseImg = $this->_model->station . '.png';

      $dataPath = $this->_getPath($datatype);
      $downloadsHtml = $this->_getDownloads($datatype);
      $explanation = $this->_getExplanation($datatype);

      $tables = [
        'Velocities' => $this->_getTable('velocities', $datatype),
        'Offsets' => $this->_getOffsetsTable($datatype),
        'Noise' => $this->_getTable('noise', $datatype),
        'Post-seismic' => $this->_getPostSeismicTable($datatype),
        'Seasonal' => $this->_getTable('seasonal', $datatype)
      ];

      $plotsHtml = '';
      if (is_file("$this->_baseDir/$dataPath/$baseImg")) {
        $navPlots = $this->_getNavPlots($datatype);
        $image = sprintf('<div class="image">
            <img src="%s/%s/%s" class="toggle" alt="Plot showing %s data (All data)" />
          </div>',
          $this->_baseUri,
          $dataPath,
          $baseImg,
          $name
        );

        $plotsHtml = $navPlots . $image . $explanation;
      }

      $tablesHtml = '';
      foreach ($tables as $tableName => $tableData) {
        if ($tableData) { // value is empty if no data in database
          $tablesHtml .= "<h3>$tableName</h3>$tableData";
        }
      }

      $html .= sprintf('
        <section class="panel" data-title="%s">
          <header>
            <h2>%s</h2>
          </header>
          %s
          <h3>Downloads</h3>
          %s
          %s
        </section>',
        $name,
        $name,
        $plotsHtml,
        $downloadsHtml,
        $tablesHtml
      );
    }

    $html .= '</div>';

    return $html;
  }

  private function _getDisclaimer () {
    return '<p><small>These results are preliminary. The station positions are
      unchecked and should not be used for any engineering applications. There
      may be errors in the antenna heights. The velocities are very dependent
      on the length of the span of observations. The presence of outliers
      (errant observations) sometimes contaminates the velocities.</small></p>';
  }

  private function _getDownloads ($datatype) {
    $html = '
      <nav class="downloads">
        <span>Raw Data:</span>
        <ul class="no-style">
          <li><a href="' . $this->_getHref($datatype, '.rneu') .'">All</a></li>
        </ul>
        <span>Detrended Data:</span>
        <ul class="no-style pipelist">
          <li><a href="' . $this->_getHref($datatype, '_N.data.gz') .'">North</a></li>
          <li><a href="' . $this->_getHref($datatype, '_E.data.gz') .'">East</a></li>
          <li><a href="' . $this->_getHref($datatype, '_U.data.gz') .'">Up</a></li>
        </ul>
        <span>Trended Data:</span>
        <ul class="no-style pipelist">
          <li><a href="' . $this->_getHref($datatype, '_N_wtrend.data.gz') .'">North</a></li>
          <li><a href="' . $this->_getHref($datatype, '_E_wtrend.data.gz') .'">East</a></li>
          <li><a href="' . $this->_getHref($datatype, '_U_wtrend.data.gz') .'">Up</a></li>
        </ul>
      </nav>';

    return $html;
  }

  private function _getExplanation ($type) {
    $components = 'north, east, and up';
    if ($type === 'itrf2008') {
      $components = 'X, Y, and Z';
    }
    return '<p>These plots depict the ' . $components . ' components of
      the station as a function of time.
      <a href="https://pubs.geoscienceworld.org/ssa/srl/article/88/3/916/284075/global-positioning-system-data-collection">More
      detailed explanation</a> &raquo;</p>
      <p>Dashed vertical lines show offsets (when present) due to:</p>
      <ul class="no-style">
        <li><mark class="green">Green</mark> &ndash; antenna changes from site logs</li>
        <li><mark class="red">Red</mark> &ndash; earthquakes</li>
        <li><mark class="blue">Blue</mark> &ndash; manually entered</li>
      </ul>';
  }

  private function _getHref ($datatype, $suffix) {
    $dataPath = $this->_getPath($datatype);
    $file = $this->_model->station . $suffix;
    $href = "$this->_baseUri/$dataPath/$file";

    // Check if img exists; if not link to no-data img
    if (preg_match('/png$/', $file) && !is_file("$this->_baseDir/$dataPath/$file")) {
      $href = "#no-data";
    }

    return $href;
  }

  private function _getLinkList () {
    $html = '<h2>Station Details</h2>';
    $links = $this->_model->links;

    $html .= '<ul>';
    foreach ($links as $key => $value) {
      $html .= '<li><a href="' . $value . '">' . $key . '</a></li>';
    }
    $html .= '</ul>';

    return $html;
  }

  private function _getMap () {
    return '<div class="map"></div>';
  }

  private function _getNavPlots ($datatype) {
    $html = '
      <nav class="plots ' . $datatype . '">
        <span>Detrended:</span>
        <ul class="no-style pipelist">
          <li><a href="' . $this->_getHref($datatype, '_30.png') . '">Past 30 days</a></li>
          <li><a href="' . $this->_getHref($datatype, '_90.png') . '">Past 90 days</a></li>
          <li><a href="' . $this->_getHref($datatype, '_365.png') . '">Past year</a></li>
          <li><a href="' . $this->_getHref($datatype, '_730.png') . '">Past 2 years</a></li>
          <li><a href="' . $this->_getHref($datatype, '.png') . '" class="selected">All data</a></li>
        </ul>
        <span>Trended:</span>
        <ul class="no-style pipelist">
          <li><a href="' . $this->_getHref($datatype, '_wtrend.png') . '">All data</a></li>
        </ul>
      </nav>';

    return $html;
  }

  private function _getNetworks () {
    $networkListHtml = '<h2>Networks</h2>';
    $networks = $this->_model->networkList;

    $networkListHtml .= '<p>This station belongs to the following network(s):</p>';
    $networkListHtml .= '<ul>';
    foreach ($networks as $network) {
      $networkListHtml .= sprintf('<li><a href="%s/%s/%s">%s</a></li>',
        $GLOBALS['MOUNT_PATH'],
        $network,
        $this->_model->station,
        $network
      );
    }
    $networkListHtml .= '</ul>';

    return $networkListHtml;
  }

  private function _getOffsetsTable ($datatype) {
    $html = '';
    $rows = $this->_model->offsets;

    if ($rows) { // offsets exist for station
      foreach ($rows as $row) {
        if ($row['datatype'] === $datatype) {
          $component = $row['component'];
          $date = str_replace('-', '', $row['date']);

          $offsets[$date]['decDate'] = $row['decdate'];
          $offsets[$date]['type'] = $row['offsettype'];
          $offsets[$date][$component . '-size'] = $row['size'];
          $offsets[$date][$component . '-uncertainty'] = $row['uncertainty'];
        }
      }
      if ($offsets) { // offsets exist for datatype
        $html = '<table>
          <tr>
            <td class="empty"></td><th>Decimal date</th><th>N offset</th>
            <th>N uncertainty</th><th>E offset</th><th>E uncertainty</th>
            <th>U offset</th><th>U uncertainty</th><th>Type</th>
          </tr>';

        foreach ($offsets as $dateStr => $tds) {
          $html .= sprintf('<tr>
              <th>%s</th>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
              <td>%s</td>
            </tr>',
            $dateStr,
            $tds['decDate'],
            $tds['N-size'],
            $tds['N-uncertainty'],
            $tds['E-size'],
            $tds['E-uncertainty'],
            $tds['U-size'],
            $tds['U-uncertainty'],
            $tds['type']
          );
        }

        $html .= '</table>';
      }
    }

    return $html;
  }

  private function _getPath ($datatype) {
    return 'networks/' . $this->_model->network . '/' . $this->_model->station .
      '/' . $datatype;
  }

  private function _getPostSeismicTable ($datatype) {
    $html = '';
    $rows = $this->_model->postSeismic;

    if ($rows) { // postseismic data exists for station
      foreach ($rows as $row) {
        if ($row['datatype'] === $datatype) {
          $component = $row['component'];
          $days = $row['doy'] - date('z') - 1; // php starts at '0'
          $time = strtotime("+" . $days . " days");
          // use 'year' from db, and calculate 'month' and 'day' from 'doy'
          $date = $row['year'] . date('md', $time);

          $postSeismic[$date]['decDate'] = $row['decdate'];
          $postSeismic[$date][$component . '-logsig'] = $row['logsig'];
          $postSeismic[$date][$component . '-logsize'] = $row['logsize'];
          $postSeismic[$date][$component . '-time'] = $row['time_constant'];
        }
      }
      if ($postSeismic) { // postseismic data exists for datatype
        $html = '<table>
          <tr>
            <td class="empty"></td><th>Decimal date</th><th>N log size</th>
            <th>N log uncertainty</th><th>N time constant</th><th>E log size</th>
            <th>E log uncertainty</th><th>E time constant</th><th>U log size</th>
            <th>U log uncertainty</th><th>U time constant</th>
          </tr>';

        foreach ($postSeismic as $dateStr => $tds) {
          $html .= sprintf('<tr>
              <th>%s</th>
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
            $dateStr,
            $tds['decDate'],
            $tds['N-logsize'],
            $tds['N-logsig'],
            $tds['N-time'],
            $tds['E-logsize'],
            $tds['E-logsig'],
            $tds['E-time'],
            $tds['U-logsize'],
            $tds['U-logsig'],
            $tds['U-time']
          );
        }

        $html .= '</table>';
      }
    }

    return $html;
  }

  private function _getTable ($table, $datatype, $lookupTable=NULL) {
    $components = [ // listed in display order on web page
      'N' => 'North',
      'E' => 'East',
      'U' => 'Up'
    ];
    $html = '';
    $rows = $this->_model->$table;

    if ($rows) {
      $html = '<table>';
      $th = '';
      $trs = [];
      foreach ($rows as $row) {
        if ($row['datatype'] === $datatype) {
          $component = $row['component'];
          $direction = $components[$component];
          $th = '<tr><td class="empty"></td>';
          $tr = '<tr class="' . strtolower($direction) . '">';
          $tr .= "<th>$direction</th>";

          unset( // don't include these values in the table
            $row['component'],
            $row['datatype'],
            $row['id'],
            $row['network'],
            $row['station']
          );
          foreach ($row as $key => $value) {
            // strip '-' out of date fields
            if (preg_match('/\d{4}-\d{2}-\d{2}/', $value)) {
              $value = str_replace('-', '', $value);
            }
            $th .= "<th>$key</th>";
            $tr .= "<td>$value</td>";
          }
          $th .= '</tr>';
          $tr .= '</tr>';
          $trs[$component] = $tr;
        }
      }
      $html .= $th;
      foreach ($components as $key => $value) {
        $html .= $trs[$key];
      }
      $html .= '</table>';
    }

    // Don't send back an empty table (happens if no data for datatype)
    if ($html === '<table></table>') {
      $html = '';
    }

    return $html;
  }

  public function render () {
    print '<div class="row">';

    print '<div class="column two-of-three">';
    print $this->_getMap();
    print '</div>';

    print '<div class="column one-of-three">';
    print $this->_getLinkList();
    print $this->_getNetworks();
    print '</div>';

    print '</div>';

    print $this->_getData();
    print $this->_getDisclaimer();
    print $this->_getBackLink();
  }
}
