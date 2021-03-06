<?php

/**
 * User data object
 */
class User extends DataObject
{
    private $username;
    private $email;
    private $password;
    private $status = "New";
    private $onwikiname = "##OAUTH##";
    private $welcome_sig = "";
    private $lastactive = "0000-00-00 00:00:00";
    private $forcelogout = 0;
    private $checkuser = 0;
    private $identified = 0;
    private $welcome_template = 0;
    private $abortpref = 0;
    private $confirmationdiff = 0;
    private $emailsig = "";
    private $oauthrequesttoken = null;
    private $oauthrequestsecret = null;
    private $oauthaccesstoken = null;
    private $oauthaccesssecret = null;
    private $oauthidentitycache = null;
    
    // cache variable of the current user - it's never going to change in the middle of a request.
    private static $currentUser;
    
    private $identityCache = null;
    
    /**
     * Summary of getCurrent
     * @param PdoDatabase $database
     * @return User The currently logged in user, or an anonymous coward with userid -1.
     */
    public static function getCurrent(PdoDatabase $database = null)
    {
        if($database === null)
        {
            $database = gGetDb();   
        }
        
        if(self::$currentUser === null)
        {
            if(isset($_SESSION['userID']))
            {
                self::$currentUser = self::getById($_SESSION['userID'], $database);
            }
            else
            {
                $anonymousCoward = new CommunityUser();
               
                self::$currentUser = $anonymousCoward;
            }
        }

        return self::$currentUser;
    }
    
    public static function getById($id, PdoDatabase $database)
    {
        if($id == "-1")
        {
            return new CommunityUser();
        }

        return parent::getById($id, $database);
    }

    public static function getCommunity()
    {
        return new CommunityUser();   
    }
    
    public static function getByUsername($username, PdoDatabase $database)
    {
        global $communityUsername;
        if($username == $communityUsername)
        {
            return new CommunityUser();
        }

        $statement = $database->prepare("SELECT * FROM user WHERE username = :id LIMIT 1;");
        $statement->bindValue(":id", $username);

        $statement->execute();

        $resultObject = $statement->fetchObject( get_called_class() );

        if($resultObject != false)
        {
            $resultObject->isNew = false;
            $resultObject->setDatabase($database); 
        }

        return $resultObject;
    }
    
    public static function getByRequestToken($requestToken, PdoDatabase $database)
    {
        $statement = $database->prepare("SELECT * FROM user WHERE oauthrequesttoken = :id LIMIT 1;");
        $statement->bindValue(":id", $requestToken);

        $statement->execute();

        $resultObject = $statement->fetchObject( get_called_class() );

        if($resultObject != false)
        {
            $resultObject->isNew = false;
            $resultObject->setDatabase($database); 
        }

        return $resultObject;
    }
    
    /**
     * @param string $status
     */
    public static function getAllWithStatus($status, PdoDatabase $database)
    {
        $statement = $database->prepare("SELECT * FROM user WHERE status = :status");
        $statement->execute( array( ":status" => $status ) );
        
        $resultObject = $statement->fetchAll( PDO::FETCH_CLASS, get_called_class() );
        
        foreach ($resultObject as $u)
        {
            $u->setDatabase($database);
            $u->isNew = false;
        }
     
        return $resultObject;
    }
    
    public static function getAllCheckusers(PdoDatabase $database)
    {
        $statement = $database->prepare("SELECT * FROM user WHERE checkuser = 1;");
        $statement->execute();
        
        $resultObject = $statement->fetchAll( PDO::FETCH_CLASS, get_called_class() );
        
        foreach ($resultObject as $u)
        {
            $u->setDatabase($database);
            $u->isNew = false;
        }
        
        return $resultObject;
    }
    
    public static function getAllInactive(PdoDatabase $database)
    {   
        $date = new DateTime();
        $date->modify("-45 days");
        
        $statement = $database->prepare(<<<SQL
            SELECT * 
            FROM user 
            WHERE lastactive < :lastactivelimit 
                AND status != 'Suspended' 
                AND status != 'Declined' 
                AND status != 'New' 
            ORDER BY lastactive ASC;
SQL
        );
        $statement->execute( array( ":lastactivelimit" => $date->format("Y-m-d H:i:s") ) );
        
        $resultObject = $statement->fetchAll( PDO::FETCH_CLASS, get_called_class() );
        
        foreach ($resultObject as $u)
        {
            $u->setDatabase($database);
            $u->isNew = false;
        }
        
        return $resultObject;
    }
    
    public function save()
    {
		if($this->isNew)
		{ // insert
			$statement = $this->dbObject->prepare(<<<SQL
                INSERT INTO `user` ( 
                    username, email, password, status, onwikiname, welcome_sig, 
                    lastactive, forcelogout, checkuser, identified, 
                    welcome_template, abortpref, confirmationdiff, emailsig, 
                    oauthrequesttoken, oauthrequestsecret, 
                    oauthaccesstoken, oauthaccesssecret
                ) VALUES (
                    :username, :email, :password, :status, :onwikiname, :welcome_sig,
                    :lastactive, :forcelogout, :checkuser, :identified, 
                    :welcome_template, :abortpref, :confirmationdiff, :emailsig, 
                    :ort, :ors, :oat, :oas
                );
SQL
            );
			$statement->bindValue(":username", $this->username);
			$statement->bindValue(":email", $this->email);
			$statement->bindValue(":password", $this->password);
			$statement->bindValue(":status", $this->status);
			$statement->bindValue(":onwikiname", $this->onwikiname);
			$statement->bindValue(":welcome_sig", $this->welcome_sig);
			$statement->bindValue(":lastactive", $this->lastactive);
			$statement->bindValue(":forcelogout", $this->forcelogout);
			$statement->bindValue(":checkuser", $this->checkuser);
			$statement->bindValue(":identified", $this->identified);
			$statement->bindValue(":welcome_template", $this->welcome_template);
			$statement->bindValue(":abortpref", $this->abortpref);
			$statement->bindValue(":confirmationdiff", $this->confirmationdiff);
			$statement->bindValue(":emailsig", $this->emailsig);
            $statement->bindValue(":ort", $this->oauthrequesttoken);
            $statement->bindValue(":ors", $this->oauthrequestsecret);
            $statement->bindValue(":oat", $this->oauthaccesstoken);
            $statement->bindValue(":oas", $this->oauthaccesssecret);
            
			if($statement->execute())
			{
				$this->isNew = false;
				$this->id = (int)$this->dbObject->lastInsertId();
			}
			else
			{
				throw new Exception($statement->errorInfo());
			}
		}
		else
		{ // update
			$statement = $this->dbObject->prepare(<<<SQL
                UPDATE `user` SET 
                    username = :username, email = :email, 
                    password = :password, status = :status,
                    onwikiname = :onwikiname, welcome_sig = :welcome_sig, 
                    lastactive = :lastactive, forcelogout = :forcelogout, 
                    checkuser = :checkuser, identified = :identified,
                    welcome_template = :welcome_template, abortpref = :abortpref, 
                    confirmationdiff = :confirmationdiff, emailsig = :emailsig, 
                    oauthrequesttoken = :ort, oauthrequestsecret = :ors, 
                    oauthaccesstoken = :oat, oauthaccesssecret = :oas 
                WHERE id = :id 
                LIMIT 1;
SQL
            );
			$statement->bindValue(":id", $this->id);
			$statement->bindValue(":username", $this->username);
			$statement->bindValue(":email", $this->email);
			$statement->bindValue(":password", $this->password);
			$statement->bindValue(":status", $this->status);
			$statement->bindValue(":onwikiname", $this->onwikiname);
			$statement->bindValue(":welcome_sig", $this->welcome_sig);
			$statement->bindValue(":lastactive", $this->lastactive);
			$statement->bindValue(":forcelogout", $this->forcelogout);
			$statement->bindValue(":checkuser", $this->checkuser);
			$statement->bindValue(":identified", $this->identified);
			$statement->bindValue(":welcome_template", $this->welcome_template);
			$statement->bindValue(":abortpref", $this->abortpref);
			$statement->bindValue(":confirmationdiff", $this->confirmationdiff);
			$statement->bindValue(":emailsig", $this->emailsig);
            $statement->bindValue(":ort", $this->oauthrequesttoken);
            $statement->bindValue(":ors", $this->oauthrequestsecret);
            $statement->bindValue(":oat", $this->oauthaccesstoken);
            $statement->bindValue(":oas", $this->oauthaccesssecret);
            
			if(!$statement->execute())
			{
				throw new Exception($statement->errorInfo());
			}
		} 
    }

    public function authenticate($password)
    {
        $result = AuthUtility::testCredentials($password, $this->password);
        
        if($result == true)
        {
            // password version is out of date, update it.
            if(!AuthUtility::isCredentialVersionLatest($this->password))
            {
                $this->password = AuthUtility::encryptPassword($password);
                $this->save();
            }
        }
        
        return $result;
    }
    
    public function touchLastLogin()
    {
        $query = "UPDATE user SET lastactive = CURRENT_TIMESTAMP() WHERE id = :id;";
        $this->dbObject->prepare($query)->execute(array(":id" => $this->id));
    }
    
    #region properties
    
    public function getUsername()
    {
        return $this->username;
    }

    public function setUsername($username)
    {
        $this->username = $username;
        $this->forcelogout = 1;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function setPassword($password)
    {
        $this->password = AuthUtility::encryptPassword($password);
    }

    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Gets the user's onwiki name
     * @return mixed
     */
    public function getOnWikiName()
    {
        if($this->oauthaccesstoken != null)
        {
            try
            {
                return $this->getOAuthOnWikiName();   
            }
            catch(Exception $ex)
            {
                // urm.. log this?
                return $this->onwikiname;
            }
        }
        
        return $this->onwikiname;
    }
    
    /**
     * This is probably NOT the function you want!
     * 
     * Take a look at getOnWikiName() instead.
     * @return string
     */
    public function getStoredOnWikiName()
    {        
        return $this->onwikiname;
    }

    public function setOnWikiName($onwikiname)
    {
        $this->onwikiname = $onwikiname;
    }

    public function getWelcomeSig()
    {
        return $this->welcome_sig;
    }

    public function setWelcomeSig($welcomesig)
    {
        $this->welcome_sig = $welcomesig;
    }

    public function getLastActive()
    {
        return $this->lastactive;
    }

    public function setLastActive($lastactive)
    {
        $this->lastactive = $lastactive;
    }

    public function getForcelogout()
    {
        return $this->forcelogout;
    }

    public function setForcelogout($forcelogout)
    {
        $this->forcelogout = $forcelogout ? 1 : 0;
    }
    
    public function getSecure()
    {
        return true;
    }

    public function getCheckuser()
    {
        return $this->checkuser;
    }

    public function setCheckuser($checkuser)
    {
        $this->checkuser = $checkuser;
    }

    public function getIdentified()
    {
        return $this->identified;
    }

    public function setIdentified($identified)
    {
        $this->identified = $identified;
    }

    public function getWelcomeTemplate()
    {
        return $this->welcome_template;
    }

    public function setWelcomeTemplate($welcometemplate)
    {
        $this->welcome_template = $welcometemplate;
    }

    public function getAbortPref()
    {
        return $this->abortpref;
    }

    public function setAbortPref($abortpref)
    {
        $this->abortpref = $abortpref;
    }

    public function getConfirmationDiff()
    {
        return $this->confirmationdiff;
    }

    public function setConfirmationDiff($confirmationdiff)
    {
        $this->confirmationdiff = $confirmationdiff;
    }

    /**
     * @return string
     */
    public function getEmailSig()
    {
        return $this->emailsig;
    }

    public function setEmailSig($emailsig)
    {
        $this->emailsig = $emailsig;
    }
    
    public function getOAuthRequestToken()
    {
        return $this->oauthrequesttoken;
    }

    public function setOAuthRequestToken($oauthrequesttoken)
    {
        $this->oauthrequesttoken = $oauthrequesttoken;
    }

    public function getOAuthRequestSecret()
    {
        return $this->oauthrequestsecret;
    }

    public function setOAuthRequestSecret($oauthrequestsecret)
    {
        $this->oauthrequestsecret = $oauthrequestsecret;
    }

    public function getOAuthAccessToken()
    {
        return $this->oauthaccesstoken;
    }

    public function setOAuthAccessToken($oauthaccesstoken)
    {
        $this->oauthaccesstoken = $oauthaccesstoken;
    }

    public function getOAuthAccessSecret()
    {
        return $this->oauthaccesssecret;
    }

    public function setOAuthAccessSecret($oauthaccesssecret)
    {
        $this->oauthaccesssecret = $oauthaccesssecret;       
    }

    #endregion
    
    #region changing access level
    
    /**
     * @param string $status
     * @param string $logaction
     */
    private function updateStatus($status, $logaction, $comment)
    {
        $oldstatus = $this->status;
        
        if($comment == null)
        {
            $comment = "Changing user access from $oldstatus to $status";
        }
        
        $this->dbObject->transactionally(function() use ($logaction, $comment, $status)
        {
            $this->status = $status;            
            $statusquery = $this->dbObject->prepare("UPDATE user SET status = :status WHERE id = :id;");
            $statusquery->bindValue(":status", $status);
            $statusquery->bindValue(":id", $this->id);
            
            $username = self::getCurrent($this->dbObject)->getUsername();
            
            $statusquery->execute();
            Logger::UserStatusChange($this->dbObject, $this, $comment, $logaction);
        });
    }
    
    public function approve()
    {
        $this->updateStatus("User", "Approved", null);
    }
    
    public function suspend($comment)
    {
        $this->updateStatus("Suspended", "Suspended", $comment);
    }
    
    public function decline($comment)
    {
        $this->updateStatus("Declined", "Declined", $comment);
    }
    
    public function promote()
    {
        $this->updateStatus("Admin", "Promoted", null);
    }
    
    public function demote($comment)
    {
        $this->updateStatus("User", "Demoted", $comment);
    }

    #endregion
    
    #region user access checks
    
    public function isAdmin()
    {
        return $this->status == "Admin";
    }
    
    public function isCheckuser()
    {
        return $this->checkuser == 1;
    }
    
    public function isIdentified()
    {
        return $this->identified == 1;   
    }
    
    public function isSuspended()
    {
        return $this->status == "Suspended";
    }
    
    public function isNew()
    {
        return $this->status == "New";
    }
    
    public function isUser()
    {
        return $this->status == "User";   
    }
    
    public function isDeclined()
    {
        return $this->status == "Declined";
    }
    
    #endregion 

    #region OAuth
    
    public function getOAuthIdentity()
    {
        if($this->oauthaccesstoken == null)
        {
            $this->clearOAuthData();
        }
        
        global $oauthConsumerToken, $oauthMediaWikiCanonicalServer;

        if($this->oauthidentitycache == null)
        {
            $this->identityCache = null;
        }
        else
        {
            $this->identityCache = unserialize($this->oauthidentitycache);
        }
        
        // check the cache
        if(
            $this->identityCache != null &&
            $this->identityCache->aud == $oauthConsumerToken &&
            DateTime::createFromFormat("U", $this->identityCache->iat) < new DateTime() &&
            DateTime::createFromFormat("U", $this->identityCache->exp) > new DateTime() &&
            $this->identityCache->iss == $oauthMediaWikiCanonicalServer
            )
        {
            return $this->identityCache;
        }
        else
        {
            $this->getIdentityCache();
            
            return $this->identityCache;
            
        }
    }
    
    private function getOAuthOnWikiName()
    {
        return $this->getOAuthIdentity()->username;
    }
    
    public function isOAuthLinked()
    {
        if ($this->onwikiname === "##OAUTH##")
        {
            return true; // special value. If an account must be oauth linked, this is true.
        }
        
        return $this->oauthaccesstoken !== null;
    }

    private function clearOAuthData()
    {
        $this->identityCache = null;
        $this->oauthidentitycache = null;
        $clearCacheQuery = "UPDATE user SET oauthidentitycache = null WHERE id = :id;";
        $this->dbObject->prepare($clearCacheQuery)->execute( array( ":id" => $this->id ) );
        
        return null;
    }
    
    private function getIdentityCache()
    {
        global $oauthConsumerToken, $oauthSecretToken, $oauthBaseUrl, $oauthBaseUrlInternal;
        
        try
        {
            $util = new OAuthUtility($oauthConsumerToken, $oauthSecretToken, $oauthBaseUrl, $oauthBaseUrlInternal);
            $this->identityCache = $util->getIdentity($this->oauthaccesstoken, $this->oauthaccesssecret);
            $this->oauthidentitycache = serialize($this->identityCache);
            $this->dbObject->
                prepare( "UPDATE user SET oauthidentitycache = :identity WHERE id = :id;" )->
                execute( array( ":id" => $this->id, ":identity" => $this->oauthidentitycache ) );
        }
        catch(UnexpectedValueException $ex)
        {
            $this->identityCache = null;
            $this->oauthidentitycache = null;
            $this->dbObject->
                prepare( "UPDATE user SET oauthidentitycache = null WHERE id = :id;" )->
                execute( array( ":id" => $this->id ) );
        }   
    }
    
    public function detachAccount()
    {
        $this->setOnWikiName($this->getOAuthOnWikiName());
        $this->setOAuthAccessSecret(null);
        $this->setOAuthAccessToken(null);
        $this->setOAuthRequestSecret(null);
        $this->setOAuthRequestToken(null);

        $this->clearOAuthData();
        
        $this->save();
    }
    
    public function oauthCanUse()
    {
        try
        {
            return in_array('useoauth', $this->getOAuthIdentity()->grants); 
        } 
        catch (Exception $ex)
        {
            return false;
        }
    }
    
    public function oauthCanEdit()
    { 
        try
        {
            return in_array('useoauth', $this->getOAuthIdentity()->grants)
                && in_array('createeditmovepage', $this->getOAuthIdentity()->grants)
                && in_array('createtalk', $this->getOAuthIdentity()->rights)
                && in_array('edit', $this->getOAuthIdentity()->rights)
                && in_array('writeapi', $this->getOAuthIdentity()->rights);} 
        catch (Exception $ex)
        {
            return false;
        }
    }
    
    public function oauthCanCreateAccount()
    { 
        try
        {
            return in_array('useoauth', $this->getOAuthIdentity()->grants)
                && in_array('createaccount', $this->getOAuthIdentity()->grants)
                && in_array('createaccount', $this->getOAuthIdentity()->rights)
                && in_array('writeapi', $this->getOAuthIdentity()->rights);
        } 
        catch (Exception $ex)
        {
            return false;
        }
    }
    
    #endregion
    
    public function getForgottenPasswordHash()
    {
        return md5($this->username . $this->email . $this->welcome_template . $this->id . $this->password);
    }

    public function getApprovalDate()
    {
        $query = $this->dbObject->prepare(<<<SQL
            SELECT timestamp 
            FROM log 
            WHERE objectid = :userid
                AND objecttype = 'User'
                AND action = 'Approved' 
            ORDER BY id DESC 
            LIMIT 1;
SQL
        );
        $query->execute( array( ":userid" => $this->id ) );
        
        $data = DateTime::createFromFormat( "Y-m-d H:i:s", $query->fetchColumn() );
        $query->closeCursor();
        
        return $data;
    }
}
