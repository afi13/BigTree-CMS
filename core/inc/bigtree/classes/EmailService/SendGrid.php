<?php
	/*
		Class: BigTree\EmailService\SendGrid
			Implements a BigTree email service for SendGrid (http://www.sendgrid.com/)
	*/

	namespace BigTree\EmailService;

	use BigTree\cURL;
	use BigTree\Email;

	class SendGrid extends Provider {

		// Implements Provider::send
		function send(Email $email) {
			// Get formatted name/email
			list($from_email,$from_name) = $this->parseAddress($email->From);

			// Get formatted reply-to
			list($reply_to,$reply_name) = $this->parseAddress($email->ReplyTo,false);

			// Build POST data
			$data = array(
				"api_user" => $this->Settings["sendgrid_api_user"],
				"api_key" => $this->Settings["sendgrid_api_key"],
				"to" => is_array($email->To) ? implode(",",$email->To) : $email->To,
				"subject" => $email->Subject,
				"html" => $email->HTML,
				"text" => $email->Text,
				"from" => $from_email,
				"fromname" => $from_name,
				"replyto" => $reply_to
			);

			if ($email->CC) {
				$data["cc"] = $email->CC;
			}

			if ($email->BCC) {
				$data["bcc"] = $email->BCC;
			}

			if ($email->Headers) {
				$data["headers"] = json_encode($email->Headers);
			}

			$response = json_decode(cURL::request("https://api.sendgrid.com/api/mail.send.json", $data, array()), true);

			if ($response["message"] === "success") {
				return true;
			} else {
				$this->Error = $response["errors"];

				return false;
			}
		}
	}