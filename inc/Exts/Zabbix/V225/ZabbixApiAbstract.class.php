<?php
/**
 * sysMonDash
 *
 * @author    nuxsmin
 * @link      http://cygnux.org
 * @copyright 2012-2016 Rubén Domínguez nuxsmin@cygnux.org
 *
 * This file is part of sysMonDash.
 *
 * sysMonDash is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysMonDash is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with sysMonDash.  If not, see <http://www.gnu.org/licenses/>.
 */


namespace Exts\Zabbix\V225;
use Exception;

/**
 * @brief   Abstract class for the Zabbix API.
 */

abstract class ZabbixApiAbstract
{

    /**
     * @brief   Anonymous API functions.
     */

    static private $anonymousFunctions = array(
        'apiinfo.version'
    );

    /**
     * @brief   Boolean if requests/responses should be printed out (JSON).
     */

    private $printCommunication = FALSE;

    /**
     * @brief   API URL.
     */

    private $apiUrl = '';

    /**
     * @brief   Default params.
     */

    private $defaultParams = array();

    /**
     * @brief   Auth string.
     */

    private $auth = '';

    /**
     * @brief   Request ID.
     */

    private $id = 0;

    /**
     * @brief   Request array.
     */

    private $request = array();

    /**
     * @brief   JSON encoded request string.
     */

    private $requestEncoded = '';

    /**
     * @brief   JSON decoded response string.
     */

    private $response = '';

    /**
     * @brief   Response object.
     */

    private $responseDecoded = NULL;

    /**
     * @brief   Extra HTTP headers.
     */

    private $extraHeaders = '';

    /**
     * @brief   SSL context.
     */

    private $sslContext = array();

    /**
     * @brief   Class constructor.
     *
     * @param   $apiUrl         API url (e.g. http://FQDN/zabbix/api_jsonrpc.php)
     * @param   $user           Username for Zabbix API.
     * @param   $password       Password for Zabbix API.
     * @param   $httpUser       Username for HTTP basic authorization.
     * @param   $httpPassword   Password for HTTP basic authorization.
     * @param   $authToken      Already issued auth token (e.g. extracted from cookies)
     * @param   $sslContext     SSL context for SSL-enabled connections
     */

    public function __construct($apiUrl='', $user='', $password='', $httpUser='', $httpPassword='', $authToken='', $sslContext=NULL)
    {
        if($apiUrl)
            $this->setApiUrl($apiUrl);

        if ($httpUser && $httpPassword)
            $this->setBasicAuthorization($httpUser, $httpPassword);

        if($sslContext)
            $this->setSslContext($sslContext);

        if ($authToken)
            $this->setAuthToken($authToken);
        elseif($user && $password)
            $this->userLogin(array('user' => $user, 'password' => $password));
    }

    /**
     * @brief   Returns the API url for all requests.
     *
     * @retval  string  API url.
     */

    public function getApiUrl()
    {
        return $this->apiUrl;
    }

    /**
     * @brief   Sets the API url for all requests.
     *
     * @param   $apiUrl     API url.
     *
     * @retval  ZabbixApiAbstract
     */

    public function setApiUrl($apiUrl)
    {
        $this->apiUrl = $apiUrl;
        return $this;
    }

    /**
     * @brief   Sets the API authorization ID.
     *
     * @param   $authToken     API auth ID.
     *
     * @retval  ZabbixApiAbstract
     */

    public function setAuthToken($authToken)
    {
        $this->authToken = $authToken;
        return $this;
    }

    /**
     * @brief   Sets the username and password for the HTTP basic authorization.
     *
     * @param   $user       HTTP basic authorization username
     * @param   $password   HTTP basic authorization password
     *
     * @retval  ZabbixApiAbstract
     */

    public function setBasicAuthorization($user, $password)
    {
        if($user && $password)
            $this->extraHeaders = 'Authorization: Basic ' . base64_encode($user.':'.$password);
        else
            $this->extraHeaders = '';

        return $this;
    }

    /**
     * @brief   Sets the context for SSL-enabled connections.
     *
     * See http://php.net/manual/en/context.ssl.php for more informations.
     *
     * @param   $context    Array with the SSL context
     *
     * @retval  ZabbixApiAbstract
     */

    public function setSslContext($context)
    {
        $this->sslContext = $context;
        return $this;
    }

    /**
     * @brief   Returns the default params.
     *
     * @retval  array   Array with default params.
     */

    public function getDefaultParams()
    {
        return $this->defaultParams;
    }

    /**
     * @brief   Sets the default params.
     *
     * @param   $defaultParams  Array with default params.
     *
     * @retval  ZabbixApiAbstract
     *
     * @throws  Exception
     */

    public function setDefaultParams($defaultParams)
    {

        if(is_array($defaultParams))
            $this->defaultParams = $defaultParams;
        else
            throw new Exception('The argument defaultParams on setDefaultParams() has to be an array.');

        return $this;
    }

    /**
     * @brief   Sets the flag to print communication requests/responses.
     *
     * @param   $print  Boolean if requests/responses should be printed out.
     *
     * @retval  ZabbixApiAbstract
     */
    public function printCommunication($print = TRUE)
    {
        $this->printCommunication = (bool) $print;
        return $this;
    }

    /**
     * @brief   Sends are request to the zabbix API and returns the response
     *          as object.
     *
     * @param   $method     Name of the API method.
     * @param   $params     Additional parameters.
     * @param   $auth       Enable authentication (default TRUE).
     *
     * @retval  stdClass    API JSON response.
     */

    public function request($method, $params=NULL, $resultArrayKey='', $auth=TRUE)
    {

        // sanity check and conversion for params array
        if(!$params)                $params = array();
        elseif(!is_array($params))  $params = array($params);

        // generate ID
        $this->id = number_format(microtime(true), 4, '', '');

        // build request array
        $this->request = array(
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
            'id'      => $this->id
        );

        // add auth token if required
        if ($auth)
            $this->request['auth'] = ($this->authToken ? $this->authToken : NULL);

        // encode request array
        $this->requestEncoded = json_encode($this->request);

        // debug logging
        if($this->printCommunication)
            echo 'API request: '.$this->requestEncoded;

        // initialize context
        $context = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/json-rpc'."\r\n".$this->extraHeaders,
                'content' => $this->requestEncoded
            )
        );
        if($this->sslContext)
            $context['ssl'] = $this->sslContext;

        // create stream context
        $streamContext = stream_context_create($context);

        // get file handler
        $fileHandler = @fopen($this->getApiUrl(), 'rb', false, $streamContext);
        if(!$fileHandler)
            throw new Exception('Could not connect to "'.$this->getApiUrl().'"');

        // get response
        $this->response = @stream_get_contents($fileHandler);

        // debug logging
        if($this->printCommunication)
            echo $this->response."\n";

        // response verification
        if($this->response === FALSE)
            throw new Exception('Could not read data from "'.$this->getApiUrl().'"');

        // decode response
        $this->responseDecoded = json_decode($this->response);

        // validate response
        if(!is_object($this->responseDecoded) && !is_array($this->responseDecoded))
            throw new Exception('Could not decode JSON response.');
        if(array_key_exists('error', $this->responseDecoded))
            throw new Exception('API error '.$this->responseDecoded->error->code.': '.$this->responseDecoded->error->data);

        // return response
        if($resultArrayKey && is_array($this->responseDecoded->result))
            return $this->convertToAssociatveArray($this->responseDecoded->result, $resultArrayKey);
        else
            return $this->responseDecoded->result;
    }

    /**
     * @brief   Returns the last JSON API request.
     *
     * @retval  string  JSON request.
     */

    public function getRequest()
    {
        return $this->requestEncoded;
    }

    /**
     * @brief   Returns the last JSON API response.
     *
     * @retval  string  JSON response.
     */

    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @brief   Convertes an indexed array to an associative array.
     *
     * @param   $indexedArray           Indexed array with objects.
     * @param   $useObjectProperty      Object property to use as array key.
     *
     * @retval  associative Array
     */

    private function convertToAssociatveArray($objectArray, $useObjectProperty)
    {
        // sanity check
        if(count($objectArray) == 0 || !property_exists($objectArray[0], $useObjectProperty))
            return $objectArray;

        // loop through array and replace keys
        $newObjectArray = array();
        foreach($objectArray as $key => $object)
        {
            $newObjectArray[$object->{$useObjectProperty}] = $object;
        }

        // return associative array
        return $newObjectArray;
    }

    /**
     * @brief   Returns a params array for the request.
     *
     * This method will automatically convert all provided types into a correct
     * array. Which means:
     *
     *      - arrays will not be converted (indexed & associatve)
     *      - scalar values will be converted into an one-element array (indexed)
     *      - other values will result in an empty array
     *
     * Afterwards the array will be merged with all default params, while the
     * default params have a lower priority (passed array will overwrite default
     * params). But there is an Exception for merging: If the passed array is an
     * indexed array, the default params will not be merged. This is because
     * there are some API methods, which are expecting a simple JSON array (aka
     * PHP indexed array) instead of an object (aka PHP associative array).
     * Example for this behaviour are delete operations, which are directly
     * expecting an array of IDs '[ 1,2,3 ]' instead of '{ ids: [ 1,2,3 ] }'.
     *
     * @param   $params     Params array.
     *
     * @retval  Array
     */

    private function getRequestParamsArray($params)
    {
        // if params is a scalar value, turn it into an array
        if(is_scalar($params))
            $params = array($params);

        // if params isn't an array, create an empty one (e.g. for booleans, NULL)
        elseif(!is_array($params))
            $params = array();

        // if array isn't indexed, merge array with default params
        if(count($params) == 0 || array_keys($params) !== range(0, count($params) - 1))
            $params = array_merge($this->getDefaultParams(), $params);

        // return params
        return $params;
    }

    /**
     * @brief   Login into the API.
     *
     * This will also retreive the auth Token, which will be used for any
     * further requests. Please be aware that by default the received auth
     * token will be cached on the filesystem.
     *
     * When a user is successfully logged in for the first time, the token will
     * be cached / stored in the $tokenCacheDir directory. For every future
     * request, the cached auth token will automatically be loaded and the
     * user.login is skipped. If the auth token is invalid/expired, user.login
     * will be executed, and the auth token will be cached again.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Parameters to pass through.
     * @param   $arrayKeyProperty   Object property for key of array.
     * @param   $tokenCacheDir      Path to a directory to store the auth token.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    final public function userLogin($params=array(), $arrayKeyProperty='', $tokenCacheDir='/tmp')
    {
        // reset auth token
        $this->authToken = '';

        // build filename for cached auth token
        if($tokenCacheDir && array_key_exists('user', $params) && is_dir($tokenCacheDir))
            $tokenCacheFile = $tokenCacheDir.'/.zabbixapi-token-'.md5($params['user'].'|'.posix_getuid());

        // try to read cached auth token
        if(isset($tokenCacheFile) && is_file($tokenCacheFile))
        {
            try
            {
                // get auth token and try to execute a user.get (dummy check)
                $this->authToken = file_get_contents($tokenCacheFile);
                $this->userGet();
            }
            catch(Exception $e)
            {
                // user.get failed, token invalid so reset it and remove file
                $this->authToken = '';
                unlink($tokenCacheFile);
            }
        }

        // no cached token found so far, so login (again)
        if(!$this->authToken)
        {
            // login to get the auth token
            $params          = $this->getRequestParamsArray($params);
            $this->authToken = $this->request('user.login', $params, $arrayKeyProperty, FALSE);

            // save cached auth token
            if(isset($tokenCacheFile))
            {
                file_put_contents($tokenCacheFile, $this->authToken);
                chmod($tokenCacheFile, 0600);
            }
        }

        return $this->authToken;
    }

    /**
     * @brief   Logout from the API.
     *
     * This will also reset the auth Token.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Parameters to pass through.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    final public function userLogout($params=array(), $arrayKeyProperty='')
    {
        $params          = $this->getRequestParamsArray($params);
        $response        = $this->request('user.logout', $params, $arrayKeyProperty);
        $this->authToken = '';
        return $response;
    }

    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method action.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function actionGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('action.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('action.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method action.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function actionExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('action.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('action.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method action.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function actionCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('action.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('action.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method action.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function actionUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('action.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('action.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method action.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function actionDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('action.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('action.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method action.validateOperations.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function actionValidateOperations($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('action.validateOperations', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('action.validateOperations', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method action.validateConditions.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function actionValidateConditions($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('action.validateConditions', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('action.validateConditions', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method action.validateOperationConditions.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function actionValidateOperationConditions($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('action.validateOperationConditions', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('action.validateOperationConditions', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method alert.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function alertGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('alert.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('alert.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method apiinfo.version.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function apiinfoVersion($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('apiinfo.version', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('apiinfo.version', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method application.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function applicationGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('application.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('application.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method application.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function applicationExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('application.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('application.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method application.checkInput.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function applicationCheckInput($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('application.checkInput', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('application.checkInput', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method application.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function applicationCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('application.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('application.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method application.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function applicationUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('application.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('application.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method application.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function applicationDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('application.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('application.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method application.massAdd.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function applicationMassAdd($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('application.massAdd', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('application.massAdd', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method configuration.export.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function configurationExport($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('configuration.export', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('configuration.export', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method configuration.import.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function configurationImport($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('configuration.import', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('configuration.import', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method dcheck.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function dcheckGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('dcheck.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('dcheck.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method dcheck.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function dcheckIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('dcheck.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('dcheck.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method dcheck.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function dcheckIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('dcheck.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('dcheck.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method dhost.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function dhostGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('dhost.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('dhost.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method dhost.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function dhostExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('dhost.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('dhost.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method discoveryrule.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function discoveryruleGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('discoveryrule.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('discoveryrule.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method discoveryrule.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function discoveryruleExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('discoveryrule.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('discoveryrule.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method discoveryrule.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function discoveryruleCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('discoveryrule.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('discoveryrule.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method discoveryrule.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function discoveryruleUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('discoveryrule.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('discoveryrule.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method discoveryrule.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function discoveryruleDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('discoveryrule.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('discoveryrule.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method discoveryrule.copy.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function discoveryruleCopy($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('discoveryrule.copy', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('discoveryrule.copy', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method discoveryrule.syncTemplates.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function discoveryruleSyncTemplates($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('discoveryrule.syncTemplates', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('discoveryrule.syncTemplates', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method discoveryrule.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function discoveryruleIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('discoveryrule.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('discoveryrule.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method discoveryrule.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function discoveryruleIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('discoveryrule.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('discoveryrule.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method discoveryrule.findInterfaceForItem.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function discoveryruleFindInterfaceForItem($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('discoveryrule.findInterfaceForItem', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('discoveryrule.findInterfaceForItem', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method drule.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function druleGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('drule.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('drule.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method drule.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function druleExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('drule.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('drule.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method drule.checkInput.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function druleCheckInput($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('drule.checkInput', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('drule.checkInput', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method drule.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function druleCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('drule.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('drule.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method drule.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function druleUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('drule.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('drule.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method drule.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function druleDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('drule.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('drule.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method drule.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function druleIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('drule.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('drule.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method drule.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function druleIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('drule.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('drule.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method dservice.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function dserviceGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('dservice.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('dservice.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method dservice.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function dserviceExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('dservice.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('dservice.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method event.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function eventGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('event.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('event.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method event.acknowledge.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function eventAcknowledge($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('event.acknowledge', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('event.acknowledge', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graph.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graph.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graph.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graph.syncTemplates.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphSyncTemplates($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graph.syncTemplates', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graph.syncTemplates', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graph.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graph.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graph.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graph.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graph.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graph.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graph.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graph.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graph.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graph.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graph.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graph.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graph.getObjects.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphGetObjects($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graph.getObjects', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graph.getObjects', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graphitem.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphitemGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graphitem.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graphitem.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graphprototype.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphprototypeGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graphprototype.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graphprototype.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graphprototype.syncTemplates.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphprototypeSyncTemplates($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graphprototype.syncTemplates', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graphprototype.syncTemplates', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graphprototype.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphprototypeDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graphprototype.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graphprototype.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graphprototype.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphprototypeUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graphprototype.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graphprototype.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graphprototype.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphprototypeCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graphprototype.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graphprototype.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graphprototype.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphprototypeExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graphprototype.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graphprototype.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method graphprototype.getObjects.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function graphprototypeGetObjects($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('graphprototype.getObjects', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('graphprototype.getObjects', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method host.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('host.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('host.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method host.getObjects.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostGetObjects($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('host.getObjects', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('host.getObjects', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method host.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('host.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('host.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method host.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('host.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('host.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method host.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('host.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('host.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method host.massAdd.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostMassAdd($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('host.massAdd', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('host.massAdd', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method host.massUpdate.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostMassUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('host.massUpdate', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('host.massUpdate', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method host.massRemove.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostMassRemove($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('host.massRemove', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('host.massRemove', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method host.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('host.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('host.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method host.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('host.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('host.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method host.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('host.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('host.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostgroup.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostgroupGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostgroup.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostgroup.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostgroup.getObjects.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostgroupGetObjects($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostgroup.getObjects', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostgroup.getObjects', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostgroup.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostgroupExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostgroup.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostgroup.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostgroup.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostgroupCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostgroup.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostgroup.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostgroup.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostgroupUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostgroup.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostgroup.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostgroup.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostgroupDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostgroup.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostgroup.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostgroup.massAdd.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostgroupMassAdd($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostgroup.massAdd', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostgroup.massAdd', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostgroup.massRemove.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostgroupMassRemove($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostgroup.massRemove', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostgroup.massRemove', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostgroup.massUpdate.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostgroupMassUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostgroup.massUpdate', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostgroup.massUpdate', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostgroup.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostgroupIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostgroup.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostgroup.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostgroup.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostgroupIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostgroup.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostgroup.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostprototype.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostprototypeGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostprototype.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostprototype.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostprototype.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostprototypeCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostprototype.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostprototype.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostprototype.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostprototypeUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostprototype.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostprototype.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostprototype.syncTemplates.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostprototypeSyncTemplates($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostprototype.syncTemplates', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostprototype.syncTemplates', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostprototype.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostprototypeDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostprototype.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostprototype.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostprototype.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostprototypeIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostprototype.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostprototype.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostprototype.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostprototypeIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostprototype.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostprototype.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method history.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function historyGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('history.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('history.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostinterface.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostinterfaceGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostinterface.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostinterface.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostinterface.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostinterfaceExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostinterface.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostinterface.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostinterface.checkInput.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostinterfaceCheckInput($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostinterface.checkInput', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostinterface.checkInput', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostinterface.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostinterfaceCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostinterface.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostinterface.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostinterface.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostinterfaceUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostinterface.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostinterface.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostinterface.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostinterfaceDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostinterface.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostinterface.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostinterface.massAdd.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostinterfaceMassAdd($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostinterface.massAdd', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostinterface.massAdd', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostinterface.massRemove.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostinterfaceMassRemove($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostinterface.massRemove', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostinterface.massRemove', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method hostinterface.replaceHostInterfaces.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function hostinterfaceReplaceHostInterfaces($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('hostinterface.replaceHostInterfaces', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('hostinterface.replaceHostInterfaces', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method image.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function imageGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('image.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('image.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method image.getObjects.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function imageGetObjects($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('image.getObjects', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('image.getObjects', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method image.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function imageExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('image.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('image.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method image.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function imageCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('image.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('image.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method image.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function imageUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('image.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('image.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method image.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function imageDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('image.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('image.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method iconmap.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function iconmapGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('iconmap.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('iconmap.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method iconmap.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function iconmapCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('iconmap.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('iconmap.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method iconmap.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function iconmapUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('iconmap.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('iconmap.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method iconmap.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function iconmapDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('iconmap.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('iconmap.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method iconmap.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function iconmapIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('iconmap.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('iconmap.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method iconmap.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function iconmapIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('iconmap.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('iconmap.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method item.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('item.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('item.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method item.getObjects.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemGetObjects($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('item.getObjects', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('item.getObjects', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method item.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('item.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('item.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method item.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('item.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('item.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method item.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('item.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('item.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method item.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('item.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('item.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method item.syncTemplates.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemSyncTemplates($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('item.syncTemplates', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('item.syncTemplates', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method item.validateInventoryLinks.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemValidateInventoryLinks($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('item.validateInventoryLinks', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('item.validateInventoryLinks', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method item.addRelatedObjects.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemAddRelatedObjects($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('item.addRelatedObjects', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('item.addRelatedObjects', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method item.findInterfaceForItem.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemFindInterfaceForItem($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('item.findInterfaceForItem', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('item.findInterfaceForItem', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method item.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('item.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('item.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method item.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('item.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('item.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method itemprototype.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemprototypeGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('itemprototype.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('itemprototype.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method itemprototype.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemprototypeExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('itemprototype.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('itemprototype.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method itemprototype.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemprototypeCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('itemprototype.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('itemprototype.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method itemprototype.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemprototypeUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('itemprototype.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('itemprototype.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method itemprototype.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemprototypeDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('itemprototype.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('itemprototype.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method itemprototype.syncTemplates.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemprototypeSyncTemplates($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('itemprototype.syncTemplates', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('itemprototype.syncTemplates', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method itemprototype.addRelatedObjects.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemprototypeAddRelatedObjects($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('itemprototype.addRelatedObjects', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('itemprototype.addRelatedObjects', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method itemprototype.findInterfaceForItem.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemprototypeFindInterfaceForItem($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('itemprototype.findInterfaceForItem', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('itemprototype.findInterfaceForItem', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method itemprototype.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemprototypeIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('itemprototype.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('itemprototype.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method itemprototype.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function itemprototypeIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('itemprototype.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('itemprototype.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method maintenance.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function maintenanceGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('maintenance.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('maintenance.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method maintenance.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function maintenanceExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('maintenance.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('maintenance.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method maintenance.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function maintenanceCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('maintenance.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('maintenance.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method maintenance.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function maintenanceUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('maintenance.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('maintenance.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method maintenance.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function maintenanceDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('maintenance.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('maintenance.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method map.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function mapGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('map.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('map.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method map.getObjects.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function mapGetObjects($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('map.getObjects', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('map.getObjects', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method map.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function mapExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('map.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('map.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method map.checkInput.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function mapCheckInput($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('map.checkInput', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('map.checkInput', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method map.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function mapCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('map.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('map.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method map.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function mapUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('map.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('map.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method map.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function mapDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('map.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('map.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method map.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function mapIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('map.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('map.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method map.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function mapIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('map.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('map.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method map.checkCircleSelementsLink.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function mapCheckCircleSelementsLink($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('map.checkCircleSelementsLink', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('map.checkCircleSelementsLink', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method mediatype.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function mediatypeGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('mediatype.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('mediatype.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method mediatype.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function mediatypeCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('mediatype.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('mediatype.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method mediatype.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function mediatypeUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('mediatype.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('mediatype.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method mediatype.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function mediatypeDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('mediatype.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('mediatype.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method proxy.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function proxyGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('proxy.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('proxy.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method proxy.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function proxyCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('proxy.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('proxy.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method proxy.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function proxyUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('proxy.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('proxy.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method proxy.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function proxyDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('proxy.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('proxy.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method proxy.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function proxyIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('proxy.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('proxy.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method proxy.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function proxyIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('proxy.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('proxy.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.validateUpdate.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceValidateUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.validateUpdate', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.validateUpdate', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.validateDelete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceValidateDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.validateDelete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.validateDelete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.addDependencies.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceAddDependencies($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.addDependencies', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.addDependencies', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.deleteDependencies.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceDeleteDependencies($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.deleteDependencies', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.deleteDependencies', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.validateAddTimes.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceValidateAddTimes($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.validateAddTimes', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.validateAddTimes', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.addTimes.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceAddTimes($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.addTimes', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.addTimes', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.getSla.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceGetSla($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.getSla', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.getSla', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.deleteTimes.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceDeleteTimes($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.deleteTimes', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.deleteTimes', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method service.expandPeriodicalTimes.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function serviceExpandPeriodicalTimes($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('service.expandPeriodicalTimes', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('service.expandPeriodicalTimes', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method screen.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function screenGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('screen.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('screen.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method screen.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function screenExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('screen.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('screen.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method screen.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function screenCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('screen.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('screen.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method screen.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function screenUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('screen.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('screen.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method screen.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function screenDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('screen.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('screen.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method screenitem.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function screenitemGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('screenitem.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('screenitem.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method screenitem.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function screenitemCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('screenitem.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('screenitem.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method screenitem.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function screenitemUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('screenitem.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('screenitem.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method screenitem.updateByPosition.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function screenitemUpdateByPosition($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('screenitem.updateByPosition', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('screenitem.updateByPosition', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method screenitem.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function screenitemDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('screenitem.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('screenitem.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method screenitem.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function screenitemIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('screenitem.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('screenitem.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method screenitem.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function screenitemIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('screenitem.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('screenitem.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method script.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function scriptGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('script.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('script.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method script.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function scriptCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('script.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('script.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method script.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function scriptUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('script.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('script.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method script.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function scriptDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('script.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('script.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method script.execute.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function scriptExecute($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('script.execute', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('script.execute', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method script.getScriptsByHosts.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function scriptGetScriptsByHosts($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('script.getScriptsByHosts', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('script.getScriptsByHosts', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method template.pkOption.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templatePkOption($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('template.pkOption', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('template.pkOption', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method template.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templateGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('template.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('template.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method template.getObjects.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templateGetObjects($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('template.getObjects', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('template.getObjects', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method template.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templateExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('template.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('template.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method template.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templateCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('template.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('template.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method template.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templateUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('template.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('template.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method template.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templateDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('template.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('template.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method template.massAdd.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templateMassAdd($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('template.massAdd', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('template.massAdd', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method template.massUpdate.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templateMassUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('template.massUpdate', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('template.massUpdate', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method template.massRemove.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templateMassRemove($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('template.massRemove', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('template.massRemove', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method template.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templateIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('template.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('template.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method template.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templateIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('template.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('template.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method templatescreen.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templatescreenGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('templatescreen.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('templatescreen.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method templatescreen.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templatescreenExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('templatescreen.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('templatescreen.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method templatescreen.copy.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templatescreenCopy($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('templatescreen.copy', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('templatescreen.copy', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method templatescreen.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templatescreenUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('templatescreen.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('templatescreen.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method templatescreen.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templatescreenCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('templatescreen.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('templatescreen.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method templatescreen.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templatescreenDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('templatescreen.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('templatescreen.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method templatescreenitem.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function templatescreenitemGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('templatescreenitem.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('templatescreenitem.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method trigger.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('trigger.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('trigger.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method trigger.getObjects.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerGetObjects($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('trigger.getObjects', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('trigger.getObjects', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method trigger.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('trigger.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('trigger.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method trigger.checkInput.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerCheckInput($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('trigger.checkInput', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('trigger.checkInput', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method trigger.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('trigger.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('trigger.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method trigger.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('trigger.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('trigger.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method trigger.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('trigger.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('trigger.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method trigger.addDependencies.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerAddDependencies($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('trigger.addDependencies', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('trigger.addDependencies', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method trigger.deleteDependencies.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerDeleteDependencies($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('trigger.deleteDependencies', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('trigger.deleteDependencies', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method trigger.syncTemplates.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerSyncTemplates($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('trigger.syncTemplates', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('trigger.syncTemplates', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method trigger.syncTemplateDependencies.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerSyncTemplateDependencies($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('trigger.syncTemplateDependencies', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('trigger.syncTemplateDependencies', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method trigger.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('trigger.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('trigger.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method trigger.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('trigger.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('trigger.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method triggerprototype.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerprototypeGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('triggerprototype.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('triggerprototype.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method triggerprototype.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerprototypeCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('triggerprototype.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('triggerprototype.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method triggerprototype.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerprototypeUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('triggerprototype.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('triggerprototype.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method triggerprototype.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerprototypeDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('triggerprototype.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('triggerprototype.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method triggerprototype.syncTemplates.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function triggerprototypeSyncTemplates($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('triggerprototype.syncTemplates', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('triggerprototype.syncTemplates', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method user.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function userGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('user.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('user.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method user.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function userCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('user.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('user.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method user.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function userUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('user.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('user.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method user.updateProfile.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function userUpdateProfile($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('user.updateProfile', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('user.updateProfile', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method user.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function userDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('user.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('user.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method user.addMedia.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function userAddMedia($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('user.addMedia', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('user.addMedia', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method user.updateMedia.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function userUpdateMedia($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('user.updateMedia', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('user.updateMedia', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method user.deleteMedia.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function userDeleteMedia($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('user.deleteMedia', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('user.deleteMedia', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method user.deleteMediaReal.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function userDeleteMediaReal($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('user.deleteMediaReal', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('user.deleteMediaReal', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method user.checkAuthentication.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function userCheckAuthentication($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('user.checkAuthentication', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('user.checkAuthentication', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method user.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function userIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('user.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('user.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method user.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function userIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('user.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('user.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usergroup.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usergroupGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usergroup.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usergroup.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usergroup.getObjects.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usergroupGetObjects($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usergroup.getObjects', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usergroup.getObjects', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usergroup.exists.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usergroupExists($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usergroup.exists', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usergroup.exists', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usergroup.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usergroupCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usergroup.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usergroup.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usergroup.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usergroupUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usergroup.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usergroup.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usergroup.massAdd.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usergroupMassAdd($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usergroup.massAdd', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usergroup.massAdd', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usergroup.massUpdate.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usergroupMassUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usergroup.massUpdate', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usergroup.massUpdate', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usergroup.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usergroupDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usergroup.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usergroup.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usergroup.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usergroupIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usergroup.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usergroup.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usergroup.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usergroupIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usergroup.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usergroup.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usermacro.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usermacroGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usermacro.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usermacro.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usermacro.createGlobal.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usermacroCreateGlobal($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usermacro.createGlobal', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usermacro.createGlobal', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usermacro.updateGlobal.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usermacroUpdateGlobal($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usermacro.updateGlobal', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usermacro.updateGlobal', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usermacro.deleteGlobal.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usermacroDeleteGlobal($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usermacro.deleteGlobal', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usermacro.deleteGlobal', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usermacro.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usermacroCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usermacro.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usermacro.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usermacro.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usermacroUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usermacro.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usermacro.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usermacro.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usermacroDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usermacro.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usermacro.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usermacro.replaceMacros.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usermacroReplaceMacros($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usermacro.replaceMacros', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usermacro.replaceMacros', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method usermedia.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function usermediaGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('usermedia.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('usermedia.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method httptest.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function httptestGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('httptest.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('httptest.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method httptest.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function httptestCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('httptest.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('httptest.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method httptest.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function httptestUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('httptest.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('httptest.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method httptest.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function httptestDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('httptest.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('httptest.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method httptest.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function httptestIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('httptest.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('httptest.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method httptest.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function httptestIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('httptest.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('httptest.isWritable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method webcheck.get.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function webcheckGet($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('webcheck.get', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('webcheck.get', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method webcheck.create.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function webcheckCreate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('webcheck.create', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('webcheck.create', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method webcheck.update.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function webcheckUpdate($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('webcheck.update', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('webcheck.update', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method webcheck.delete.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function webcheckDelete($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('webcheck.delete', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('webcheck.delete', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method webcheck.isReadable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function webcheckIsReadable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('webcheck.isReadable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('webcheck.isReadable', $params, $arrayKeyProperty, $auth);
    }
    
    /**
     * @brief   Reqeusts the Zabbix API and returns the response of the API
     *          method webcheck.isWritable.
     *
     * The $params Array can be used, to pass parameters to the Zabbix API.
     * For more informations about these parameters, check the Zabbix API
     * documentation at https://www.zabbix.com/documentation/.
     *
     * The $arrayKeyProperty can be used to get an associatve instead of an
     * indexed array as response. A valid value for the $arrayKeyProperty is
     * is any property of the returned JSON objects (e.g. name, host,
     * hostid, graphid, screenitemid).
     *
     * @param   $params             Zabbix API parameters.
     * @param   $arrayKeyProperty   Object property for key of array.
     *
     * @retval  stdClass
     *
     * @throws  Exception
     */

    public function webcheckIsWritable($params=array(), $arrayKeyProperty='')
    {
        // get params array for request
        $params = $this->getRequestParamsArray($params);

        // check if we've to authenticate
        $auth = in_array('webcheck.isWritable', self::$anonymousFunctions) ? FALSE : TRUE;

        // request
        return $this->request('webcheck.isWritable', $params, $arrayKeyProperty, $auth);
    }
    

}

?>