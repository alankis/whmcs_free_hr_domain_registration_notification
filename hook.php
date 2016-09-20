<?php

/**
 * Use new Laravel DBAL
 */
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * DETERMINE IF ORDER HAS A FREE .HR DOMAIN CHECKED
 * run hook on "PreShoppingCartCheckout", then check if domain 
 * has flag (besplatna domena), if has, remove this part and 
 * domain to global variable
 * WE USE THIS HOOK TO HAVE CLEAN DOMAIN NAME BEFORE SAVING ORDER!!!!
 *
 */

function hook_IsFreeDomainRegistration(array $params)
{
	/**
	 * save order domain in $domain variable
	 */
	$domain = $_SESSION['cart']['products']['0']['domain'];

	/**
	 * does $domain has 'besplatna domena' part in value?
	 */
	if(preg_match('/\(besplatna domena\)$/m', $domain))
	{
		/**
		 * remove 'besplatna domena' part from domain
		 */
		$domain = preg_replace('/\(besplatna domena\)$/m', '', $domain);
		
		/**
		 * save free domain flag 
		 */
		$isfreedomainregistration = "true";

		/**
		 * return clean domain value to $_SESSION params
		 */
		$_SESSION['cart']['products']['0']['domain'] = $domain;

		/**
		 * save $isfreedomainregistration flag to $_SESSION
		 */
		$_SESSION['freedomainflag'] = $isfreedomainregistration;

		/**
		 * save orderId variable which we need for DBAL interaction
		 */
		$_SESSION['orderId'] = $_SESSION['orderdetails']['OrderID'];


		
	}
	else
	{
		$isfreedomainregistration = "false";
		$_SESSION['freedomainflag'] = $isfreedomainregistration;
	}

}


function hook_logFreeDomainRegistration(array $params) 
{
	/**
	 * Log all hook avaliable params
	 */
	//$c = print_r($params, true);

	$c = "Free domain registration: " . $_SESSION['freedomainflag'];
	
	$freedomainregistrationflag = $_SESSION['freedomainflag'];


	$orderid = $params['OrderID'];

	/**
	 * Dummy $note variable whhich get stored in notes.tblthosting
	 */
	$note =  date('d/m/y') . " - uz uslugu naručena registracija besplatne domene.";

	if ($freedomainregistrationflag === "true")
	{
		/**
		 * Get to the database with PDO
		 */
		$pdo = Capsule::connection()->getPdo();

		try
		{
			$statement = $pdo->prepare('UPDATE tblhosting SET notes = :notes WHERE orderid = :orderid');

			$statement->execute([':notes' => $note, ':orderid' => $orderid]); 
		}
		catch (\Exception $e)
		{
			echo "Huh hoh! {$e->getMessage()}";
		}
	}
}

function hook_NotifyFreeDomainRegistrationActivation(array $params)
{	
	//$p = print_r($params, true);

	//mail('alan.kish38@gmail.com', 'PREMODULECREATEDEBUG', $p);

	$id = $params['params']['accountid'];

	//mail('alan.kish38@gmail.com', 'PREMODULECREATEDEBUG', $id);

	/**
	 * if tblhosting = "Pending" and "uz uslugu naručena registracija besplatne domene." then notify owner of system (mail function)
	 */
	$pdo = Capsule::connection()->getPdo();

		$statement = $pdo->prepare('SELECT id, notes, domain, regdate FROM tblhosting WHERE id = :id');

		$statement->execute([':id' => $id]);

		$result = $statement->fetch(PDO::FETCH_ASSOC);

		if(isset($result['domain']))
		{
			$domain = $result['domain'];
		}

		if(isset($result['regdate']))
		{
			$registrationdate = $result['regdate'];
		}
		
		$subject = "WHMCS - Registracija besplatne .hr domene " . " \"$domain\"";

 		$message = "Uz hosting uslugu naručenu" . " \"$registrationdate\"" . " vezanu uz domenu" . " \"$domain\"" . "naručena je opcija registracije besplatne **.hr** domene.";

		/**
		 * Set needle
		 */
		$re = "/[0-9]{1,2}\\/[0-9]{1,2}\\/[0-9]{1,2}\\s\\-\\suz uslugu naručena registracija besplatne domene./m";

		/**
		 * is $result['note'] set?
		 */
		if (isset($result['notes']))
		{
			/**
			 * set haystack
			 */
			$note = $result['notes'];
			
			/**
			 * If free domain registration note is in result, notify system owner
			 */
			if (preg_match($re, $note))
			{
				mail('info@infonet.hr', $subject, $message);
			}
	
		}

}


/*
 * run first hook
 */
add_hook("PreShoppingCartCheckout", 1, "hook_IsFreeDomainRegistration");



/*
 * run second hook 
 */
 add_hook("AfterShoppingCartCheckout", 1, "hook_logFreeDomainRegistration");


/**
 *
 * run third hook - Notify system owner only on module activation, so they are notified on every order
 */
 add_hook("PreModuleCreate", 1, "hook_NotifyFreeDomainRegistrationActivation");
