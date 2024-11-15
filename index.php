<?php
	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\SMTP;
	use PHPMailer\PHPMailer\Exception;
	require __DIR__ . '/vendor/autoload.php';
	
	if (file_exists(__DIR__ . "/settings.json")){  //make sure settings file exists
		$settings = file_get_contents(__DIR__ . '/settings.json'); //read settings file
		$settings = JSON_DECODE($settings, TRUE);	//turning json values into array
		if (!is_array($settings)){
			echo "Error opening settings.json file.   Refer to settings_TEMPLATE.json.";	//notify to create settings json file
			error_log("Error opening settings.json file.   Refer to settings_TEMPLATE.json.", 0);		//notify to create settings json file
			exit;
		}	else	{
			foreach($settings as $key => $value){	//iterate thorugh all array members
				define($key, $value);	//create constants of the settings
			}
		}
	}	else	{
		echo "Error opening settings.json file.   Refer to settings_TEMPLATE.json.";		//notify to create settings json file
		error_log("Error opening settings.json file.   Refer to settings_TEMPLATE.json.", 0);		//notify to create settings json file
		exit;
	}

	if (file_exists(__DIR__ . "/results.json")) {
		$json = file_get_contents(__DIR__ . '/results.json'); //open results from last change
		$json = JSON_DECODE($json, true);
		if (!is_array($json)){
			$json = [];
		}
	}	else	{
		$json = [];
	}

	$now = date("m/d/Y h:i:s A" , strtotime("now"));
	foreach (urls as $url){
		if (array_key_exists($url, $json)){
			$pass = $json[$url];
		}	else	{
			$pass = ["result" => "new", "time" => $now];
		}

		$status = checkSite($url);
		$newResult = in_array($status, range(200, 399)) ? true : false;

		if ($pass["result"] === $newResult){ //no change between last run and now
			$json[$url] = $pass;	//this is set so that non changing results dont get wiped out when file is written
			echo "$url - no change.   move on";		
		}	elseif (($pass["result"] === true || $pass["result"] == "new") && !$newResult){	//last run was a success this run is a failure
			$subject = "Website Uptime Monitor - Site Down";
			$body = "There was an attempting to test the site $url.   The site returned a status code of $status\r\nLast successful test was at {$pass["time"]}.";
			sendMessage($subject, $body);
			$json[$url] = ["result" => $newResult, "time" => $now];
			echo "website was up and is now down";
		}	elseif ((!$pass["result"] || $pass["result"] == "new")&& $newResult){	//last run was a failure and this run is a success
			$subject = "Website Uptime Monitor - Site Back Up";
			$body = "The site $url is back up.   The site returned a status code of $status\r\nSite had been down since {$pass["time"]}.";
			sendMessage($subject, $body);
			$json[$url] = ["result" => $newResult, "time" => $now];
			echo "website was down and is now up";
		}
	}
	logResults($json);

	function checkSite($url){
		try {
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_POST, false);
			$resp = curl_exec($curl);
			$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			curl_close($curl);
			return $status;
		} catch (Exception $e) {
			return 0;
		}
	}

	function logResults($json){		
		$file = fopen(__DIR__ . "/results.json", "w");	//open file 
		fwrite($file, JSON_ENCODE($json));	//write json
		fclose($file);	//close file
	}

	function sendMessage($subject, $body){
		$html = nl2br($body);				//generate html version  of plain text email body
		$mail = new PHPMailer(true);
		try {
			//Server settings
			$mail->isSMTP();                                            //Send using SMTP
			$mail->Host       = smtp_server;                     //Set the SMTP server to send through
			if (smtp_auth){
				$mail->SMTPAuth   = true;                                   //Enable SMTP authentication
				$mail->Username   = smtp_username;                     //SMTP username
				$mail->Password   = smtp_password;                               //SMTP password
				$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
			}
			$mail->Port       = smtp_port;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
			$mail->setFrom(sender_email, sender_name);	
		
			foreach (recipients as $recip){
				$mail->addAddress($recip["recip_email"], $recip["recip_name"]);     //Add a recipient
			}
			//Content
			$mail->isHTML(true);                                  //Set email format to HTML
			$mail->Subject = $subject;
			$mail->Body    = $html;
			$mail->AltBody = $body;
		
			$mail->send();
			echo 'Message has been sent';
		} catch (Exception $e) {
			echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
		}
	}
?>
