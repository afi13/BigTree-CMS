<?php
	/*
		Class: BigTree\User
			Provides an interface for users.
			Easily extended for custom user systems by overriding the static $Table property.
	*/

	namespace BigTree;

	use PasswordHash;

	/**
	 * @property-read int $ID
	 * @property-read string $OriginalPassword
	 */

	class User extends BaseObject {

		public static $Table = "bigtree_users";

		protected $ID;
		protected $OriginalPassword;

		public $Alerts;
		public $ChangePasswordHash;
		public $Company;
		public $DailyDigest;
		public $Email;
		public $Level;
		public $Name;
		public $Password;
		public $Permissions;

		/*
			Constructor:
				Builds a User object referencing an existing database entry.

			Parameters:
				user - Either an ID (to pull a record) or an array (to use the array as the record)
		*/

		function __construct($user = null) {
			if ($user !== null) {
				// Passing in just an ID
				if (!is_array($user)) {
					$user = SQL::fetch("SELECT * FROM ".static::$Table." WHERE id = ?", $user);
				}

				// Bad data set
				if (!is_array($user)) {
					trigger_error("Invalid ID or data set passed to constructor.", E_USER_ERROR);
				} else {
					$this->ID = $user["id"];
					$this->OriginalPassword = $user["password"];

					$this->Email = $user["email"];
					$this->Password = $user["password"];
					$this->Name = $user["name"] ?: null;
					$this->Company = $user["company"] ?: null;
					$this->Level = $user["level"] ?: 0;
					$this->Permissions = $user["permissions"] ? json_decode($user["permissions"], true) : null;
					$this->Alerts = $user["alerts"] ? json_decode($user["alerts"], true) : null;
					$this->DailyDigest = $user["daily_digest"] ? true : false;
					$this->ChangePasswordHash = $user["change_password_hash"] ?: null;
					
					// Verify a correct permissions array
					if (!is_array($this->Permissions)) {
						$this->Permissions = array();
					}
					
					if (!is_array($this->Permissions["page"])) {
						$this->Permissions["page"] = array();
					}
					
					if (!is_array($this->Permissions["module"])) {
						$this->Permissions["module"] = array();
					}
					
					if (!is_array($this->Permissions["resources"])) {
						$this->Permissions["resources"] = array();
					}
					
					if (!is_array($this->Permissions["module_gbp"])) {
						$this->Permissions["module_gbp"] = array();
					}
					
					// Verify alerts is an array as well
					if (!is_array($this->Alerts)) {
						$this->Alerts = array();
					}
				}
			}
		}

		/*
			Function: create
				Creates a user.

			Parameters:
				email - Email Address
				password - Password
				name - Name
				company - Company
				level - User Level (0 for regular, 1 for admin, 2 for developer)
				permission - Array of permissions data
				alerts - Array of alerts data
				daily_digest - Whether the user wishes to receive the daily digest email

			Returns:
				A USer object of the newly created user or false if a user already exists with the provided email
		*/

		static function create($email, $password = "", $name = "", $company = "", $level = 0, $permissions = array(), $alerts = array(), $daily_digest = "") {
			global $bigtree;

			// See if user exists already
			if (SQL::exists(static::$Table, array("email" => $email))) {
				return false;
			}

			// Hash the password.
			$phpass = new PasswordHash($bigtree["config"]["password_depth"], true);
			$password = $phpass->HashPassword(trim($password));

			// Create the user
			$id = SQL::insert(static::$Table, array(
				"email" => $email,
				"password" => $password,
				"name" => Text::htmlEncode($name),
				"company" => Text::htmlEncode($company),
				"level" => intval($level),
				"permissions" => $permissions ?: "{}",
				"alerts" => $alerts ?: "{}",
				"daily_digest" => ($daily_digest ? "on" : "")
			));

			AuditTrail::track(static::$Table, $id, "created");

			return new User($id);
		}

		/*
			Function: delete
				Deletes the user
		*/

		function delete() {
			SQL::delete(static::$Table, $this->ID);
			AuditTrail::track(static::$Table, $this->ID, "deleted");
		}

		/*
			Function: getByEmail
				Gets a user entry for a given email address

			Parameters:
				email - Email address

			Returns:
				A user array or false if the user was not found
		*/

		static function getByEmail($email) {
			$user = SQL::fetch("SELECT * FROM ".static::$Table." WHERE LOWER(email) = ?", trim(strtolower($email)));
			
			if ($user) {
				return new User($user);
			}

			return false;
		}

		/*
			Function: getByHash
				Gets a user entry for a change password hash

			Parameters:
				hash - Change Password Hash

			Returns:
				A user array or false if the user was not found
		*/

		static function getByHash($hash) {
			$user = SQL::fetch("SELECT * FROM ".static::$Table." WHERE change_password_hash = ?", $hash);

			if ($user) {
				return new User($user);
			}

			return false;
		}

		/*
			Function: initPasswordReset
				Creates a new password change hash and sends an email to the user.
		*/

		function initPasswordReset() {
			global $bigtree;

			// Update the user's password reset hash code
			$hash = $this->setPasswordHash();

			// Get site title for email
			$site_title = SQL::fetchSingle("SELECT `nav_title` FROM `bigtree_pages` WHERE id = '0'");

			$login_root = ($bigtree["config"]["force_secure_login"] ? str_replace("http://", "https://", ADMIN_ROOT) : ADMIN_ROOT)."login/";

			$html = file_get_contents(Router::getIncludePath("admin/email/reset-password.html"));
			$html = str_ireplace("{www_root}", WWW_ROOT, $html);
			$html = str_ireplace("{admin_root}", ADMIN_ROOT, $html);
			$html = str_ireplace("{site_title}", $site_title, $html);
			$html = str_ireplace("{reset_link}", $login_root."reset-password/$hash/", $html);

			$reply_to = "no-reply@".(isset($_SERVER["HTTP_HOST"]) ? str_replace("www.", "", $_SERVER["HTTP_HOST"]) : str_replace(array("http://www.", "https://www.", "http://", "https://"), "", DOMAIN));

			$email = new Email;

			$email->To = $this->Email;
			$email->Subject = "Reset Your Password";
			$email->HTML = $html;
			$email->From = $email->Settings["bigtree_from"] ?: $reply_to;
			$email->ReplyTo = $reply_to;

			$email->send();
		}

		/*
			Function: removeBans
				Removes all login bans for the user
		*/

		function removeBans() {
			SQL::delete("bigtree_login_bans", array("user" => $this->ID));
		}

		/*
			Function: setPasswordHash
				Creates a change password hash for a user

			Returns:
				A change password hash.
		*/

		function setPasswordHash() {
			$hash = md5(microtime().$this->Password);
			SQL::update("bigtree_users", $this->ID, array("change_password_hash" => $hash));

			return $hash;
		}

		/*
			Function: save
				Saves the current object properties back to the database.
		*/

		function save() {
			if (empty($this->ID)) {
				$new = static::create(
					$this->Email,
					$this->Password,
					$this->Name,
					$this->Company,
					$this->Level,
					$this->Permissions,
					$this->Alerts,
					$this->DailyDigest
				);

				if ($new === false) {
					trigger_error(Text::translate("Failed to create user due to invalid email address."), E_USER_WARNING);
				} else {
					$this->inherit($new);
				}
			} else {
				global $bigtree;

				$update_values = array(
					"email" => $this->Email,
					"name" => Text::htmlEncode($this->Name),
					"company" => Text::htmlEncode($this->Company),
					"level" => intval($this->Level),
					"permissions" => (array) $this->Permissions,
					"alerts" => (array) $this->Alerts,
					"daily_digest" => $this->DailyDigest ? "on" : ""
				);

				if ($this->Password != $this->OriginalPassword) {
					$phpass = new PasswordHash($bigtree["config"]["password_depth"], true);
					$update_values["password"] = $phpass->HashPassword(trim($this->Password));
				}

				SQL::update(static::$Table, $this->ID, $update_values);
				AuditTrail::track("bigtree_users", $this->ID, "updated");
			}
		}

		/*
			Function: update
				Updates the user properties and saves the changes to the database.

			Parameters:
				email - Email Address
				password - Password
				name - Name
				company - Company
				level - User Level (0 for regular, 1 for admin, 2 for developer)
				permission - Array of permissions data
				alerts - Array of alerts data
				daily_digest - Whether the user wishes to receive the daily digest email

			Returns:
				true if successful. false if there was an email collision.
		*/

		function update($email, $password = "", $name = "", $company = "", $level = 0, $permissions = array(), $alerts = array(), $daily_digest = "") {
			// See if there's an email collission
			if (SQL::fetchSingle("SELECT COUNT(*) FROM ".static::$Table." WHERE `email` = ? AND `id` != ?", $email, $this->ID)) {
				return false;
			}

			$this->Email = $email;
			$this->Name = $name;
			$this->Company = $company;
			$this->Level = $level;
			$this->Permissions = $permissions;
			$this->Alerts = $alerts;
			$this->DailyDigest = $daily_digest;

			if ($password != "") {
				$this->Password = $password;
			}

			$this->save();

			return true;
		}

		/*
			Function: updateProfile
				Updates the logged-in user's name, company, digest setting, and (optionally) password.

			Parameters:
				name - Name
				company - Company
				daily_digest - Whether to receive the daily digest (truthy value) or not (falsey value)
				password - Password (leave empty or false to not update)
		*/

		static function updateProfile($name, $company = "", $daily_digest = "", $password = false) {
			global $bigtree;

			$user = Auth::user()->ID;

			// Make sure a user is logged in
			if (is_null($user)) {
				trigger_error("Method updateProfile not available outside logged-in user context.");

				return false;
			}

			$update_values = array(
				"name" => Text::htmlEncode($name),
				"company" => Text::htmlEncode($company),
				"daily_digest" => $daily_digest ? "on" : "",
			);

			if ($password !== "" && $password !== false) {
				$phpass = new PasswordHash($bigtree["config"]["password_depth"], true);
				$update_values["password"] = $phpass->HashPassword($password);
			}

			SQL::update("bigtree_users", $user, $update_values);
			
			return true;
		}

		/*
			Function: validatePassword
				Validates a password against the security policy.

			Parameters:
				password - Password to validate.

			Returns:
				true if it passes all password criteria.
		*/

		static function validatePassword($password) {
			global $bigtree;

			$policy = $bigtree["security-policy"]["password"];
			$failed = false;

			// Check length policy
			if ($policy["length"] && strlen($password) < $policy["length"]) {
				$failed = true;
			}
			// Check case policy
			if ($policy["multicase"] && strtolower($password) === $password) {
				$failed = true;
			}
			// Check numeric policy
			if ($policy["numbers"] && !preg_match("/[0-9]/", $password)) {
				$failed = true;
			}
			// Check non-alphanumeric policy
			if ($policy["nonalphanumeric"] && ctype_alnum($password)) {
				$failed = true;
			}

			return !$failed;
		}

	}