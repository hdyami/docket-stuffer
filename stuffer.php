<?php 

  // prepare db connection
  // we'll cache the queries to stay speedy with prepared statements
  $db = new PDO('mysql:dbname=docket_stuffer;host=localhost;charset=utf8', 'root', '');

  $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


// $docket = '14-28';
// //$docket = '10-127';

// $cookie = getcookie($docket);
// postcomment($cookie, $docket, NULL);

// Set path to CSV file
$csvFile = 'docketcomments.csv';
$csv = readCSV($csvFile);

print "<pre>";
print_r($csv);
print "</pre>";

print "submitting  14-28 \n";
foreach ($csv as $key => $row) {
  $docket = '14-28';
  // if confirmation row in csv is blank
  $statement = $db->prepare("SELECT email FROM docket_submitted WHERE email = :email");
  $statement->execute(array(':email' => $row[0]));

  $db_row = $statement->fetch();

  if ($db_row == '') {
    $cookie = getcookie($docket);
    postcomment($cookie, $docket, $row);
  } else {
    print "row has already been submitted";
  }

}

print "begin 10-127 \n";
foreach ($csv as $key => $row) {
  $docket = '10-127';
  
  // if confirmation row in csv is blank
  $statement = $db->prepare("SELECT email FROM docket_submitted_10_127 WHERE email = :email");
  $statement->execute(array(':email' => $row[0]));

  $db_row = $statement->fetch();

  if ($db_row == '') {
    $cookie = getcookie($docket);
    postcomment($cookie, $docket, $row);
  } else {
    print "row has already been submitted";
  }

}

// END LOGIC
// ---------------------------------------------
// FUNCTIONS
function readCSV($csvFile){
  $file_handle = fopen($csvFile, 'r');
  while (!feof($file_handle) ) {
    $line_of_text[] = fgetcsv($file_handle, 1024);
  }
  fclose($file_handle);
  unset($line_of_text[0]);

  return $line_of_text;
}

function getcookie($docket) {
  $curl = curl_init();
  $url = 'http://apps.fcc.gov/ecfs/upload/begin?procName=$docket&filedFrom=X';
  curl_setopt($curl, CURLOPT_URL,$url);
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($curl, CURLOPT_MAXREDIRS, 1);
  // curl_setopt($curl, CURLOPT_NOBODY, true);
  curl_setopt($curl, CURLOPT_HEADER, true);

  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
   
  $curl_response = curl_exec($curl);
  curl_close($curl);

  preg_match('/^Set-Cookie:\s*([^;]*)/mi', $curl_response, $cookie);


  if (strstr($curl_response, "Cannot open connection") !== FALSE) {
    sleep(5);
    print "waiting and trying again for cookie";
    $curl = curl_init();
    $url = 'http://apps.fcc.gov/ecfs/upload/begin?procName=$docket&filedFrom=X';
    curl_setopt($curl, CURLOPT_URL,$url);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_MAXREDIRS, 1);
    // curl_setopt($curl, CURLOPT_NOBODY, true);
    curl_setopt($curl, CURLOPT_HEADER, true);

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
     
    $curl_response = curl_exec($curl);
    curl_close($curl);

    // print $curl_response;
    preg_match('/^Set-Cookie:\s*([^;]*)/mi', $curl_response, $cookie);

    return $cookie[1];
  }

  return $cookie[1];
}


function postcomment($cookie, $docket, $row) {
  // prepare db connection
  // we'll cache the queries to stay speedy with prepared statements
  $db = new PDO('mysql:dbname=docket_stuffer;host=localhost;charset=utf8', 'root', '');

  $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);



  $curl = curl_init();

  $post_url = 'http://apps.fcc.gov/ecfs/upload/process;'.$cookie;

  curl_setopt($curl, CURLOPT_URL,$post_url);
  curl_setopt($curl, CURLOPT_POST, 1);

  $states = array("AK", "AL", "AZ", "AR", "CA", "CO", "CT", "DC", "DE", "FL", "GA", "GU", "HI", "ID", "IL", "IN", "IA", "KS", "KY", "LA", "ME", "MD", "MA", "MI", "MN", "MS", "MO", "MT", "NE", "NH", "NJ", "NM", "NV", "NY", "NC", "ND", "OH", "OK", "OR", "PA", "PR", "RI", "SC", "SD", "TN", "TX", "UT", "VA", "VI", "VT", "WA", "WI", "WV", "WY", "AS", "FM", "MH", "MP", "PW",);

  foreach ($states as $key => $state) {
    if ($state == $row[5]) {
      $state = ++$key;
    }
  }

  curl_setopt($curl, CURLOPT_POSTFIELDS, 
    http_build_query(array('action%3Aprocess' => "Continue",
                           'briefComment' => $row[7],
                           'address.zip' => $row[6],
                           'address.state.id' => $state,
                           'address.city' => $row[4],
                           'address.line1' => $row[3],
                           'applicant' => $row[1] . " " . $row[2],
                           'procName' => $docket,
                           )));

  // curl_setopt($curl, CURLOPT_HEADER, true);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
  // curl_setopt($curl, CURLOPT_MAXREDIRS, 1);

  // submit the row data to the express comment form
  $curl_response = curl_exec($curl);
  curl_close($curl);
  
  print_r($curl_response);

  // get cofirm/finalization url/token from the previous curl response
  $finalurl = strstr($curl_response, "/ecfs/upload/confirm;jsessionid");
  $newfinalurl = substr($finalurl, 0, strpos($finalurl, '">Confirm </a>'));

  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL,'http://apps.fcc.gov/'.$newfinalurl);
  // curl_setopt($curl, CURLOPT_POST, 1);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_HEADER, true);

  // Final submisison, expect confirmation number
  $curl_response = curl_exec($curl);
  curl_close($curl);

  // print_r($curl_response);

  $conf_string = strstr($curl_response, "Confirmation number: ");


  $confirmation = substr($conf_string, 0, strpos($conf_string, '</h2>'));
  
  if ($docket == '14-28') {
    
    $insertSubmittedDocket = $db->prepare('INSERT INTO docket_submitted (
                                                      email,
                                                      first_name,
                                                      last_name,
                                                      address1,
                                                      city,
                                                      state,
                                                      zip,
                                                      comment,
                                                      confirmation
                                                      ) VALUES (
                                                        :email,
                                                        :first_name,
                                                        :last_name,
                                                        :address1,
                                                        :city,
                                                        :state,
                                                        :zip,
                                                        :comment,
                                                        :confirmation
                                                      )');

    $insertSubmittedDocket->execute(array('email' => $row[0],
                                  'first_name' => $row[1],
                                  'last_name' => $row[2],
                                  'address1' => $row[3],
                                  'city' => $row[4],
                                  'state' => $state,
                                  'zip' => $row[6],
                                  'comment' => $row[7],
                                  'confirmation' => $confirmation,
                                  ));
    return;
  }

  if ($docket == '10-127') {
    
    $insertSubmittedDocket = $db->prepare('INSERT INTO docket_submitted_10_127(
                                                      email,
                                                      first_name,
                                                      last_name,
                                                      address1,
                                                      city,
                                                      state,
                                                      zip,
                                                      comment,
                                                      confirmation
                                                      ) VALUES (
                                                        :email,
                                                        :first_name,
                                                        :last_name,
                                                        :address1,
                                                        :city,
                                                        :state,
                                                        :zip,
                                                        :comment,
                                                        :confirmation
                                                      )');

    $insertSubmittedDocket->execute(array('email' => $row[0],
                                  'first_name' => $row[1],
                                  'last_name' => $row[2],
                                  'address1' => $row[3],
                                  'city' => $row[4],
                                  'state' => $state,
                                  'zip' => $row[6],
                                  'comment' => $row[7],
                                  'confirmation' => $confirmation,
                                  ));
    return;
  }
}

?>