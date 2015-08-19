<?php
/*


   _____ _                     _____  _    _ _____  
  / ____| |                   |  __ \| |  | |  __ \ 
 | (___ | | ___   _ _ __   ___| |__) | |__| | |__) |
  \___ \| |/ / | | | '_ \ / _ \  ___/|  __  |  ___/ 
  ____) |   <| |_| | |_) |  __/ |    | |  | | |     
 |_____/|_|\_\\__, | .__/ \___|_|    |_|  |_|_|     
               __/ | |                              
              |___/|_|                              


Version: 1.0
GitHub : https://github.com/Kibioctet/SkypePHP


*/

class skype {
	
	/**
	* Connects you to the specified Skype account
	* In case of problem, a PHP error is returned
	*
	* $skypeUsername -> your skype username
	* $skypePassword -> your skype password
	*/
	function __construct($skypeUsername, $skypePassword) {
		$skypeUsername = htmlspecialchars($skypeUsername);
		
		$authentification = $this->web("https://login.skype.com/login?client_id=578134&redirect_uri=https%3A%2F%2Fweb.skype.com", "GET", null, true);
		
		$tokens = Array(
			"username" => $skypeUsername,
			"password" => $skypePassword,
			"pie" => $this->extractToken($authentification, "pie"),
			"etm" => $this->extractToken($authentification, "etm"),
			"js_time" => $this->extractToken($authentification, "js_time"),
			"timezone_field" => "+02|00",
			"client_id" => 578134,
			"redirect_uri" => "http://web.skype.com/"
		);

		$authentification = $this->web("https://login.skype.com/login?client_id=578134&redirect_uri=https%3A%2F%2Fweb.skype.com", "POST", $tokens, true);
		
		preg_match('`Set-Cookie: refresh-token=(.+); path=/; secure; httponly`isU', $authentification, $skypeToken);
		if (isset($skypeToken[1])) {
			$skypeToken = $skypeToken[1];
			
			$authentification = $this->web("https://client-s.gateway.messenger.live.com/v1/users/ME/endpoints","POST", "{}", true, true, Array("Authentication: skypetoken=$skypeToken"));
			
			preg_match('`registrationToken=(.+);`isU', $authentification, $registrationToken);
			
			$this->registrationToken = $registrationToken[1];
			$this->skypeToken = $skypeToken;
			$this->identifiant = $skypeUsername;
		} else {
			trigger_error("Skype ($skypeUsername) : Authentication failed", E_USER_WARNING);
			exit;
		}
	}
	
	/**
	* extractToken
	*
	* Extract tokens from a Skype login page
	* $page -> html code with the tokens
	* $nom -> name of the token
	*/
	private function extractToken($page, $nom) {
		preg_match('`<input type="hidden" name="'.$nom.'" id="'.$nom.'" value="(.+)"/>`isU', $page, $resultat);
		
		return isset($resultat[1]) ? $resultat[1] : false;
	}
	
	/**
	* web
	*
	* Creates a web request with cURL
	* $url -> url for the request
	* $mode -> method of the request
	* $post -> post data (optional)
	* $showHeaders -> display or not the response headers
	* $suivre -> follow redirects
    * $headers -> send custom headers or not
	*/
	private function web($url, $mode = "GET", $post = null, $showHeaders = false, $suivre = true, $headers = null) {
		if (!function_exists("curl_init")) exit("You need cURL for use SkypePHP");
		
		if (isset($this->registrationToken) && isset($this->skypeToken)) {
			if (!isset($headers) or !is_array($headers)) {
				$headers = Array();
			}
			$headers = Array("X-Skypetoken: {$this->skypeToken}", "RegistrationToken: registrationToken={$this->registrationToken}", "Content-Length: ".strlen($post), "Content-Type: application/x-www-form-urlencoded");
		}
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		if (!is_null($headers)) curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $mode);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_HEADER, $showHeaders);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, $suivre);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$resultat = curl_exec($curl);
		curl_close($curl);
		
		return $resultat;
	}
	
	/**
	* timestamp
	*
	* Return current timestamp
	*/
	private function timestamp() {
		return str_replace(".", "", microtime(1));
	}
	
	/**
	* URLtoUser
	*
	* Convert a Skype conversation URL to the name of the user
	* $url -> conversation url
	*/
	private function URLtoUser($url) {
		return str_replace("https://db3-client-s.gateway.messenger.live.com/v1/users/ME/contacts/", "", str_replace("8:", "", str_replace("19:", "", $url)));
	}
	
	
	
	
	
	
	
	
	/**
	* sendMessage
	*
	* Send a message
	* $user -> username of the recipient
	* $message -> message to send
	*/
	public function sendMessage($user, $message) {
		$user = $this->URLtoUser($user);
		$cMode = (strstr($user, "@thread.skype") ? 19 : 8);
		$messageID = $this->timestamp();
		$requete = json_decode($this->web("https://client-s.gateway.messenger.live.com/v1/users/ME/conversations/$cMode:$user/messages", "POST", json_encode(Array("content" => $message, "messagetype" => "RichText", "contenttype" => "text", "clientmessageid" => $messageID))), true);
		
		return isset($requete["OriginalArrivalTime"]) ? $messageID : false;
	}
	
	/**
	* getMessagesList
	*
	* Get messages from a conversation
	* $user -> username of the recipient
	* $size -> number of messages to display (min: 1, max: 200)
	*/
	public function getMessagesList($user, $size = 200) {
		$user = $this->URLtoUser($user);
		if ($size > 200 or $size < 1) $size = 200;
		$cMode = (strstr($user, "@thread.skype") ? 19 : 8);
		$requete = json_decode($this->web("https://client-s.gateway.messenger.live.com/v1/users/ME/conversations/$cMode:$user/messages?startTime=0&pageSize=$size&view=msnp24Equivalent&targetType=Passport|Skype|Lync|Thread"), true);
		
		
		if (!isset($requete["message"])) {
			return $requete;
		} else {
			trigger_error("Skype ({$this->identifiant}) : ".__FUNCTION__." -> {$requete["message"]}", E_USER_WARNING);
			return false;
		}
	}
	
	/**
	* createGroup
	*
	* Create a group
	* $usersArray -> must contain an array with the users to add
	*/
	public function createGroup($usersArray) {
		if (is_array($usersArray)) {
			
			foreach ($usersArray as $user) {
				$members["members"][] = Array("id" => "8:".$this->URLtoUser($user), "role" => "User");
			}
			
			$members["members"][] = Array("id" => "8:{$this->identifiant}", "role" => "Admin");
			
			$this->web("https://client-s.gateway.messenger.live.com/v1/threads", "POST", json_encode($members), true);
			return true;
		} else {
			trigger_error("Skype ({$this->identifiant}) : ".__FUNCTION__." -> the group members list is not an Array", E_USER_WARNING);
			return false;
		}
	}
	
	/**
	* getGroupInfo
	*
	* Gets the group information
	* $group -> the group username
	*/
	public function getGroupInfo($group) {
		$requete = json_decode($this->web("https://client-s.gateway.messenger.live.com/v1/threads/19:$group?view=msnp24Equivalent", "GET"), true);
		
		return (!isset($requete["code"]) ? $requete : false);
	}
	
	/**
	* addUser
	*
	* Adds a user to a group
	* $group -> the group username
	* $user -> username to add
	*/
	public function addUser($group, $user) {
		$user = $this->URLtoUser($user);
		
		$requete = $this->web("https://client-s.gateway.messenger.live.com/v1/threads/19:$group/members/8:$user", "PUT");
		
		return (empty($requete) ? true : false);		
	}
	
	/**
	* kickUser
	*
	* Kick a user from a group (if you can)
	* $group -> the group username
	* $user -> username to kick
	*/
	public function kickUser($group, $user) {
		$user = $this->URLtoUser($user);
		
		$requete = json_decode($this->web("https://client-s.gateway.messenger.live.com/v1/threads/19:$group/members/8:$user", "DELETE"), true);
		
		return (empty($requete) ? true : false);
	}
	
	/**
	* leaveGroup
	*
	* Exits a group
	* $group -> the group username
	*/
	public function leaveGroup($group) {
		$requete = $this->kickUser($group, $this->identifiant);
		
		return ($requete ? true : false);
	}
	
	/**
	* ifGroupHistoryDisclosed
	*
	* Choose if the history of a group is disclosed or not (if you can)
	* $historydisclosed -> "true" or "false"
	*/
	public function ifGroupHistoryDisclosed($group, $historydisclosed) {
		$requete = $this->web("https://client-s.gateway.messenger.live.com/v1/threads/19:22b5a075499b4da78b18d6013e1e69ad@thread.skype/properties?name=historydisclosed", "PUT", json_encode(Array("historydisclosed" => $historydisclosed)));
		
		return (empty($requete) ? true : false);
	}	
}