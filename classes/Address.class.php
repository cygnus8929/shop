<?php
/**
 * Class to handle billing and shipping addresses.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;


/**
 * Class to handle address formatting.
 * @package shop
 */
class Address
{
    /** Person name.
     * @var string */
    private $name = '';

    /** Company name.
     * @var string */
    private $company = '';

    /** Address Line 1.
     * @var string */
    private $address1 = '';

    /** Address Line 2.
     * @var string */
    private $address2 = '';

    /** City name.
     * @var string */
    private $city = '';

    /** State/Province.
     * @var string */
    private $state = '';

    /** Postal code.
     * @var string */
    private $zip = '';

    /** Country code.
     * @var string */
    private $country = '';

    /** Phone number.
     * @var string */
    private $phone = '';

    /** User ID.
     * @var integer */
    private $uid = 0;

    /** Flag indicates this is a default Billing address.
     * @var integer */
    private $billto_def = 0;

    /** Flag indicates this is a default Shipping address.
     * @var integer */
    private $shipto_def = 0;

    /** Address record ID.
     * @var integer */
    private $addr_id = 0;

    /** DB table name, to facilitate inherited classes.
     * @var string */
    protected $table = 'shop.addresses';

    /** Address field names.
     * @var array */
    protected $_fields = array(
        'name', 'company', 'address1', 'address2',
        'city', 'state', 'zip', 'country', 'phone',
    );


    /**
     * Load the supplied address values, if any, into the properties.
     * `$data` may be an array or a json_encoded string.
     *
     * @param   string|array    $data   Address data
     */
    public function __construct($data=array())
    {
        global $_SHOP_CONF, $_USER;

        if (!is_array($data)) {
            // Allow for a JSON string to be provided.
            $data = json_decode($data, true);
        }
        if (is_array($data)) {
            $this->setVars($data);
        }
        if ($this->addr_id < 1) {
            // in case an empty object is being created, set the user ID
            // and defaults for the selections
            $this->setUid($_USER['uid']);
        }
    }


    /**
     * Set all the properties from a provided array.
     *
     * @param   array   $data   Array of property name->value pairs
     * @return  object  $this
     */
    public function setVars($data)
    {
        global $_SHOP_CONF;

        if (isset($data['uid'])) {
            $this->setUid($data['uid']);
        }
        if (isset($data['addr_id'])) {
            $this->setID($data['addr_id']);
        }
        if (isset($data['billto_def'])) {
            $this->setBilltoDefault($data['billto_def']);
        }
        if (isset($data['shipto_def'])) {
            $this->setShiptoDefault($data['shipto_def']);
        }
        if (isset($data['name'])) {
            $this->setName($data['name']);
        }
        if (isset($data['address1'])) {
            $this->setAddress1($data['address1']);
        }
        if (isset($data['address2'])) {
            $this->setAddress2($data['address2']);
        }
        if (isset($data['city'])) {
            $this->setCity($data['city']);
        }
        if (isset($data['state'])) {
            $this->setState($data['state']);
        }
        if (isset($data['zip'])) {
            $this->setPostal($data['zip']);
        }
        if (isset($data['country'])) {
            $this->setCountry($data['country']);
        }
        if (isset($data['phone'])) {
            $this->setPhone($data['phone']);
        }
        return $this;
    }


    /**
     * Get a specific address by ID.
     *
     * @param   integer $addr_id    Address ID to retrieve
     * @return  object      Address object, empty if not found
     */
    public static function getInstance($addr_id)
    {
        global $_TABLES;
        static $addrs = array();

        $addr_id = (int)$addr_id;
        if ($addr_id > 0) {
            if (isset($addrs[$addr_id])) {
                return new self($addrs[$addr_id]);
            } else {
                $res = DB_query("SELECT *
                    FROM {$_TABLES['shop.address']}
                    WHERE addr_id = '{$addr_id}'");
                if ($res) {
                    $A = DB_fetchArray($res, true);
                    $addrs[$addr_id] = $A;
                    return new self($A);
                } else {
                    return new self;
                }
            }
        } else {
            return new self;
        }
    }


    /**
     * Set the record ID.
     *
     * @param   integer $id     DB record ID
     * @return  object  $this
     */
    public function setID($id)
    {
        $this->addr_id = (int)$id;
        return $this;
    }


    /**
     * Get the record ID.
     *
     * @return  integer     Record ID
     */
    public function getID()
    {
        return (int)$this->addr_id;
    }


    /**
     * Set the user ID.
     *
     * @param   integer $uid    User ID
     * @return  object  $this
     */
    public function setUid($uid)
    {
        $this->uid = (int)$uid;
        return $this;
    }


    /**
     * Get the user ID.
     *
     * @return  integer     User ID
     */
    public function getUid()
    {
        return (int)$this->uid;
    }


    /**
     * Set the person's name.
     *
     * @param   string  $name   Person's name
     * @return  object  $this
     */
    public function setName($name)
    {
        $this->name = (string)$name;
        return $this;
    }


    /**
     * Get the name for the address.
     *
     * @return  string      Name value
     */
    public function getName()
    {
        return (string)$this->name;
    }


    /**
     * Set the company name.
     *
     * @param   string  $name   Company name
     * @return  object  $this
     */
    public function setCompany($name)
    {
        $this->company = (string)$name;
        return $this;
    }


    /**
     * Get the company name.
     *
     * @return  string      Company name
     */
    public function getCompany()
    {
        return (string)$this->company;
    }


    /**
     * Set the first address line.
     *
     * @param   string  $address    Address value
     * @return  object  $this
     */
    public function setAddress1($address)
    {
        $this->address1 = ucwords(strtolower($address));
        return $this;
    }


    /**
     * Get the first address line.
     *
     * @return  string      Address value
     */
    public function getAddress1()
    {
        return (string)$this->address1;
    }


    /**
     * Set the second address line.
     *
     * @param   string  $address    Address value
     * @return  object  $this
     */
    public function setAddress2($address)
    {
        $this->address2 = ucwords(strtolower($address));
        return $this;
    }


    /**
     * Get the second address line.
     *
     * @return  string      Address value
     */
    public function getAddress2()
    {
        return (string)$this->address2;
    }


    /**
     * Set the city value.
     *
     * @param   string  $city   City name
     * @return  object  $this
     */
    public function setCity($city)
    {
        $this->city = ucwords(strtolower($city));
        return $this;
    }


    /**
     * Get the city name.
     *
     * @return  string      City name
     */
    public function getCity()
    {
        return (string)$this->city;
    }


    /**
     * Set the state/province name.
     *
     * @param   string  $state      State/Province name
     * @return  object  $this
     */
    public function setState($state)
    {
        $this->state = strtoupper((string)$state);
        return $this;
    }


    /**
     * Get the state/province.
     *
     * @return  string      State/Province value
     */
    public function getState()
    {
        return (string)$this->state;
    }


    /**
     * Set the postal code.
     *
     * @param   string  $zip    Postal (zip) code
     * @return  object  $this
     */
    public function setPostal($zip)
    {
        $this->zip = (string)$zip;
        return $this;
    }


    /**
     * Get the postal code.
     *
     * @return  string      Postal (zip) code
     */
    public function getPostal()
    {
        return (string)$this->zip;
    }


    /**
     * Get the 5-character main US zip code.
     * For other countries just return the zip code with spaces removed.
     *
     * @return  string      4-character zip code.
     */
    public function getZip5()
    {
        if ($this->country == 'US') {
            return substr($this->zip, 0, 5);
        } else {
            return str_replace(' ', '', $this->zip);
        }
    }


    /**
     * Get the zip+4 digits.
     *
     * @return  string      4-character zip+4 code.
     */
    public function getZip4()
    {
        if ($this->country == 'US') {
            $pos = strpos($this->zip, '-');
            if ($pos !== false) {
                $retval = substr($this->zip, $pos+1, 4);
            } else {
                $retval = '';
            }
        }
        return $retval;
    }


    /**
     * Set the country code.
     *
     * @param   string  $code   2-letter country code
     * @return  object  $this
     */
    public function setCountry($code)
    {
        $this->country = strtoupper((string)$code);
        return $this;
    }


    /**
     * Get the country code.
     *
     * @return  string      2-letter country code
     */
    public function getCountry()
    {
        return (string)strtoupper($this->country);
    }


    /**
     * Set the phone number.
     *
     * @param   string  $phone  Telephone number
     * @return  object  $this
     */
    public function setPhone($phone)
    {
        $this->phone = (string)$phone;
        return $this;
    }


    /**
     * Get the phone number.
     *
     * @return  string      Telephone number
     */
    public function getPhone()
    {
        return (string)$this->phone;
    }


    /**
     * Check if this is the default billing or shipping address.
     * Returns an integer to be compatible with the database field.
     *
     * @param   string  $type   Type of address, either `billto` or `shipto`
     * @return  integer     1 if this is the default, 0 if not
     */
    public function isDefault($type)
    {
        if ($type == 'billto') {
            return $this->isDefaultBillto();
        } else {
            return $this->isDefaultShipto();
        }
    }


    /**
     * Check if this is the default billing address.
     * Returns an integer to be compatible with the database field.
     *
     * @return  integer     1 if this is the default, 0 if not
     */
    public function isDefaultShipto()
    {
        return $this->shipto_def ? 1 : 0;
    }


    /**
     * Check if this is the default billing address.
     * Returns an integer to be compatible with the database field.
     *
     * @return  integer     1 if this is the default, 0 if not
     */
    public function isDefaultBillto()
    {
        return $this->billto_def ? 1 : 0;
    }


    /**
     * Set this Address as the default biling address.
     *
     * @param   boolean $value  True to set as default, False to unset
     * @return  object  $this
     */
    public function setBilltoDefault($value)
    {
        $this->billto_def = $value ? 1 : 0;
        return $this;
    }


    /**
     * Set this Address as the default shipping address.
     *
     * @param   boolean $value  True to set as default, False to unset
     * @return  object  $this
     */
    public function setShiptoDefault($value)
    {
        $this->shipto_def = $value ? 1 : 0;
        return $this;
    }


    /**
     * Set this Address as the default shipping or billing address.
     *
     * @param   string  $type   Address type, `billto` or `shipto`
     * @param   boolean $value  True to set as default, False to unset
     */
    public function setDefault($type, $value = true)
    {
        if ($type == 'billto') {
            return $this->setBilltoDefault($value);
        } else {
            return $this->setShiptoDefault($value);
        }
    }


    /**
     * Convert the address fields to a single JSON string.
     *
     * @param   boolean $escape     True to escape for DB storage
     * @return  string  Address string
     */
    public function toJSON($escape=false)
    {
        $str = json_encode($this->toArray());
        if ($escape) {
            $str = DB_escapeString($str);
        }
        return $str;
    }


    /**
     * Get all address records belonging to a specific user ID.
     *
     * @param   integer $uid    User ID
     * @return  array       Array of Address objects
     */
    public static function getByUser($uid)
    {
        global $_TABLES;
        static $cache = array();
        if (isset($cache[$uid])) {
            return $cache[$uid];
        }

        $uid = (int)$uid;
        $retval = array();
        if ($uid > 1) {
            $res = DB_query(
                "SELECT * FROM {$_TABLES['shop.address']} WHERE uid=$uid"
            );
            while ($A = DB_fetchArray($res, false)) {
                $retval[$A['addr_id']] = new self($A);
            }
        }
        $cache[$uid] = $retval;
        return $retval;
    }


    /**
     * Get the city, state, zip line, formatted by country.
     *
     * @param   string  $sep    Optional override to default separator
     * @return  string  Formatted string for city, state, zip
     */
    private function getCityLine($sep="\n")
    {
        switch($this->country) {
        case 'US':
        case 'CA':
        case 'AU':
        case 'TW':
            $parts = array(
                $this->city,
                $this->state,
                $this->zip,
            );
            $retval = implode(' ', array_filter($parts));
            break;
        case 'GB':
        case 'CO':
        case 'IE':
        case '':        // default if no country code given
            $parts = array(
                $this->city,
                $this->zip,
            );
            $retval = implode($sep, array_filter($parts));
            break;
        default:
            $parts = array(
                $this->zip,
                $this->city,
                $this->state,
            );
            $retval = implode(' ', array_filter($parts));
            break;
        }
        return $retval;
    }


    /**
     * Render the address as text, separated by the specified separator.
     * Request can be for a single address field, an array of fields,
     * or a keyword to get multiple standard parts. Keywords include:
     *   - street: gets only the street address
     *   - address: gets street address, city/state/zip line and country
     *   - all : gets all components except the phone number
     * The special key `cityline` can be used also to get the city/state/zip
     * line according to the country format.
     *
     * @param   string  $part   Optional part of address to retrieve
     * @param   string  $sep    Line separator, simple `\n` by default.
     * @return  string      HTML formatted address
     */
    public function toText($part="all", $sep="\n")
    {
        $parts = array();
        if (is_string($part)) {
            $part = array($part);
        }
        foreach ($part as $p) {
            switch ($p) {
            case 'all':
                $parts[] = 'name';
                $parts[] = 'company';
            case 'address':
                $parts[] = 'address1';
                $parts[] = 'address2';
                $parts[] = 'cityline';
                $parts[] = 'country';
                break;
            case 'street':
                $parts[] = 'address1';
                $parts[] = 'address2';
                break;
            default:
                $parts[] = $p;
                break;
            }
        }

        $retval = array();
        foreach ($parts as $part) {
            switch ($part) {
            case 'cityline':
                $retval[] = $this->getCityLine($sep);
                break;
            case 'country':
                if ($this->country != Config::get('country')) {
                    $retval[] = Country::getInstance($this->country)->getName();
                }
                break;
            default:
                if (isset($this->$part) && !empty($this->$part)) {
                    $retval[] = $this->$part;
                }
                break;
            }
        }
        $retval = implode($sep, $retval);
        return $retval;
    }


    /**
     * Get the address in HTML format. Uses `<br />\n` betwen lines.
     *
     * @uses    self::toText()
     * @param   string  $part   Optional part of address to retrieve
     * @return  string      Address as HTML
     */
    public function toHTML($part='all')
    {
        return $this->toText($part, "<br />\n");
    }


    /**
     * Get a MD5 hash of the address. Used for caching keys.
     *
     * @uses    self::toText()
     * @return  string      MD5 hash of the text address
     */
    public function toHash()
    {
        return md5($this->toText('address'));
    }


    /**
     * Get the parsed parts of a name field.
     *
     * @param   string  $req    Requested part, NULL for all
     * @return  string|array    Requested part, or array of all parts
     */
    public function parseName($req = NULL)
    {
        static $parts = array();;

        if (!isset($parts[$this->name])) {
            $parts[$this->name] = array();
            $status = LGLIB_invokeService(
                'lglib', 'parseName',
                array(
                    'name' => $this->name,
                ),
                $parts[$this->name], $svc_msg
            );
        }
        if ($req == NULL) {
            // return all parts
            return $parts[$this->name];
        } else {
            // return only the selected part
            return isset($parts[$this->name][$req]) ? $parts[$this->name][$req] : '';
        }
    }


    /**
     * Edit an address record.
     *
     * @return  string  HTML for editing form
     */
    public function Edit()
    {
        $have_state_country = false;
        if ($this->uid > 1) {
            $Addr = Customer::getInstance()->getDefaultAddress('shipto');
            if ($Addr->getID() > 0) {
                $this->setState($Addr->getState())
                    ->setCountry($Addr->getCountry());
                $have_state_country = true;
            }
        }
        if (!$have_state_country) {
            $this->setState(Config::get('state'))
                 ->setCountry(Config::get('country'));
        }

        $T = new Template;
        $T->set_file('form', 'editaddress.thtml');
        $T->set_var(array(
            'addr_id' => $this->addr_id,
            'uid' => $this->uid,
            'name' => $this->name,
            'company' => $this->company,
            'address1' => $this->address1,
            'address2' => $this->address2,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'country_options' => Country::optionList($this->country),
            'state_options' => State::optionList($this->country, $this->state),
            'phone' => $this->phone,
            'def_shipto_chk' => $this->isDefaultShipto() ? 'checked="checked"' : '',
            'def_billto_chk' => $this->isDefaultBillto() ? 'checked="checked"' : '',
            'cancel_url' => SHOP_getUrl(SHOP_URL . '/account.php?addresses'),
            'return' => SHOP_getVar($_GET, 'return'),
        ) );
        $T->parse('output', 'form');
        return  $T->finish($T->get_var('output'));
    }


    /**
     * Save the address to the database.
     *
     * @return  integer     Record ID of address, zero on error
     */
    public function Save()
    {
        global $_TABLES;

        if ($this->uid < 2) {
            // Got an invalid user ID, don't save.
            return 0;
        }

        if ($this->addr_id > 0) {
            $sql1 = "UPDATE {$_TABLES['shop.address']} SET ";
            $sql2 = " WHERE addr_id='" . $this->addr_id . "'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['shop.address']} SET ";
            $sql2 = '';
        }

        $sql = "uid = '" . (int)$this->uid . "',
                name = '" . DB_escapeString($this->name) . "',
                company = '" . DB_escapeString($this->company) . "',
                address1 = '" . DB_escapeString($this->address1) . "',
                address2 = '" . DB_escapeString($this->address2) . "',
                city = '" . DB_escapeString($this->city) . "',
                state = '" . DB_escapeString($this->state) . "',
                country = '" . DB_escapeString($this->country) . "',
                phone = '" . $this->getPhone() . "',
                zip = '" . DB_escapeString($this->zip) . "',
                billto_def = '" . $this->isDefaultBillto() . "',
                shipto_def = '" . $this->isDefaultShipto() . "'";
        $sql = $sql1 . $sql . $sql2;
        //echo $sql;die;
        DB_query($sql);
        if (!DB_error()) {
            if ($this->addr_id == 0) {
                $this->addr_id = DB_insertID();
            }

            // If this is the new default address, turn off the other default
            foreach (array('billto', 'shipto') as $type) {
                if ($this->isDefault($type)) {
                    $sql = "UPDATE {$_TABLES['shop.address']}
                        SET {$type}_def = 0 WHERE
                        uid = {$this->uid}
                        AND addr_id <> {$this->addr_id}
                        AND {$type}_def = 1";
                    DB_query($sql);
                }
            }
            Cache::clear('shop.user_' . $this->uid);
            return $this->addr_id;
        } else {
            return 0;
        }
    }


    /**
     *  Return the properties array.
     *  Keys can be prefixed with billto_ or shipto_ to match Orders schema.
     *
     *  @return array   Address properties
     */
    public function toArray()
    {
        return array(
            'id'        => $this->addr_id,
            'name'      => $this->name,
            'company'   => $this->company,
            'address1'  => $this->address1,
            'address2'  => $this->address2,
            'city'      => $this->city,
            'state'     => $this->state,
            'zip'       => $this->zip,
            'country'   => $this->country,
            'phone'     => $this->phone,
        );
    }


    /**
     * Load data from an array into the object.
     *
     * @param   array   $A      Array of data
     * @param   string  $prefix Optional prefix used in array indexes
     * @return  object  $this
     */
    public function fromArray($A, $prefix='')
    {
        if ($prefix !== '') {
            $prefix .= '_';
        }
        if (isset($A[$prefix . 'id'])) {
            $this->addr_id = (int)$A[$prefix . 'id'];
        }
        foreach ($this->_fields as $fldname) {
            $var = $prefix . $fldname;
            if (isset($A[$var])) {
                $this->$fldname = $A[$var];
            } else {
                $this->$fldname = '';
            }
        }
        return $this;
    }


    /**
     * Validate the address components.
     *
     * @param   boolean $required   True if an address is required at all
     * @return  string      List of invalid items, or empty string for success
     */
    public function isValid($required=true)
    {
        global $LANG_SHOP, $_SHOP_CONF;

        $invalid = array();
        $retval = '';

        if (empty($this->name) && empty($this->company)) {
            $invalid[] = 'name_or_company';
        }
        if (
            $required && empty($this->address1)
        ) {
            $invalid[] = 'address1';
        }
        if (
            $required && empty($this->city)
        ) {
            $invalid[] = 'city';
        }
        if (
            $required && empty($this->state)
        ) {
            $invalid[] = 'state';
        }
        if (
            $required && empty($this->zip)
        ) {
            $invalid[] = 'zip';
        }
        if (
            $required && $this->country == ''
        ) {
            $invalid[] = 'country';
        }

        if (!empty($invalid)) {
            foreach ($invalid as $id) {
                $retval .= '<li> ' . $LANG_SHOP[$id] . '</li>' . LB;
            }
            $retval = '<ul>' . $retval . '</ul>';
        }
        return $retval;
    }


    /**
     * Delete an address by id.
     * Called when the user deletes one of their billing or shipping addresses.
     *
     * @param   integer $id     Record ID of address to delete
     */
    public function Delete()
    {
        global $_TABLES;

        if ($this->addr_id < 1) {
            return false;
        }
        DB_delete($_TABLES['shop.address'], 'addr_id', $this->addr_id);
        Cache::clear('shop.user_' . $this->uid);
        Cache::clear('shop.address_' . $this->uid);
        return true;
    }


    /**
     * Check if this address object matches the supplied object.
     *
     * @param   object  $Addr   Address to compare to this one
     * @param   boolean $all    True to include uid, defaults, etc.
     * @return  boolean     True on match, False if any fields differ
     */
    public function Matches($Addr, $all=false)
    {
        // Check all address fields, return false if any don't match
        if (
            $this->address1 != $Addr->getAddress1() ||
            $this->address2 != $Addr->getAddress2() ||
            $this->city     != $Addr->getCity() ||
            $this->state    != $Addr->getState() ||
            $this->zip      != $Addr->getPostal() ||
            $this->country  != $Addr->getCountry()
        ) {
            return false;
        }

        // Examine the non-address fields if checking all
        if ($all &&
            (
                $this->billto_def != $Addr->isDefaultBillto() ||
                $this->shopto_def != $Addr->isDefaultShipto()
            )
        ) {
            return false;
        }

        // No inequalitis found, return true
        return true;
    }


    /**
     * Copy the contents of another address object into this one.
     *
     * @param   object  $Addr   Address to copy to this one
     * @param   boolean $all    True to include uid, defaults, etc.
     * @return  object  $this
     */
    public function Copy($Addr, $all=false)
    {
        $this->setAddress1($Addr->getAddress1())
            ->setAddress2($Addr->getAddress2())
            ->setCity($Addr->getCity())
            ->setState($Addr->getState())
            ->setPostal($Addr->getPostal())
            ->setCountry($Addr->getCountry())
            ->setPhone($Addr->getPhone());
        if ($all) {
            $this->setBilltoDefault($Addr->isDefaultBillto())
                ->setShiptoDefault($Addr->isDefaultShipto())
                ->setUid($Addr->getUid());
        }
        return $this;
    }


    /**
     * Use an address validation service to verify an address.
     *
     */
    public function Validate()
    {
        global $_SHOP_CONF;

        if (SHOP_getVar($_SHOP_CONF, 'address_validator') != '') {
            $cls = 'Shop\\Validators\\' . $_SHOP_CONF['address_validator'];
            if (class_exists($cls)) {
                $AV = new $cls($this);
                $AV->Validate();
                return $AV->getAddress();
            }
        }
        return $this;       // default if no validator used
    }


    /**
     * Get the option elements for an address selection list.
     *
     * @param   integer $uid        Customer user ID
     * @param   string  $type       'billto' or 'shipto'
     * @param   integer $sel_id     Preselected address ID
     */
    public static function optionList($uid, $type, $sel_id=0)
    {
        $retval = '';
        $Addresses = self::getByUser($uid);
        foreach ($Addresses as $Addr) {
            if (
                ($sel_id == 0 && $Addr->isDefault($type)) ||
                ($sel_id > 0 && $sel_id == $Addr->getID())
            ) {
                $sel = 'selected="selected"';
            } else {
                $sel = '';
            }
            $retval .= '<option value="' . $Addr->getID() . '" ' . $sel . '>' .
                $Addr->toText() . '</option>' . LB;
        }
        return $retval;
    }


    /**
     * Create an address from IP Geolocation information.
     * The full address is not created, only city, state, country and zip.
     *
     * @param   string  $ip     IP address, current remote address if empty
     * @return  object      Address object
     */
    public static function fromGeoLocation($ip=NULL)
    {
        $Geo = GeoLocator::getProvider();
        if ($ip === NULL) {
            $Geo->withIP($ip);
        }
        $addr = $Geo->geoLocate();
        if ($addr['status']) {
            $retval = new self(array(
                'city' => $addr['city_name'],
                'state' => $addr['state_code'],
                'country' => $addr['country_code'],
                'zip' => $addr['zip'],
            ) );
        } else {
            $retval = new self;
        }
        return $retval;
    }

}
