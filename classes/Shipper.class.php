<?php
/**
 * Class to handle shipping costs based on quantity, total weight and class.
 * First iteration only allows for a number of "units" per product.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.7.1
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Class for product and category sales.
 * @package shop
 */
class Shipper
{
    /** Minimim possible effective date/time.
     * @const string */
    const MIN_DATETIME = '1970-01-01 00:00:00';

    /** Maximum possible effective date/time.
     * @const string */
    const MAX_DATETIME = '2037-12-31 23:59:59';

    /** Minimum units. Used since zero indicates free.
     * @const float */
    const MIN_UNITS = .0001;

    /** Base tag used for caching.
     * @var string */
    static $base_tag = 'shipping';

    /** Property fields. Accessed via __set() and __get()
     * @var array */
    private $properties;

    /** Indicate whether the current object is a new entry or not.
     * @var boolean */
    public $isNew;

    /** Individual rate element.
     * @var array */
    public $rates;


    /**
     * Constructor. Sets variables from the provided array.
     *
     * @param  array   DB record
     */
    public function __construct($A=array())
    {
        $this->properties = array();
        $this->isNew = true;

        if (is_array($A) && !empty($A)) {
            // DB record passed in, e.g. from _getSales()
            $this->setVars($A);
            $this->isNew = false;
        } elseif (is_numeric($A) && $A > 0) {
            // single ID passed in, e.g. from admin form
            if ($this->Read($A)) $this->isNew = false;
        } else {
            // New entry, set defaults
            $this->id = 0;
            $this->enabled = 1;
            $this->name = '';
            $this->use_fixed = 1;
            $this->valid_from = self::MIN_DATETIME;
            $this->valid_to = self::MAX_DATETIME;
            $this->auth_grp = 2;    // Default = All users
            $this->rates = array(
                (object)array(
                    'dscp'  => 'Rate 1',
                    'units' => 10,
                    'rate'  => 5,
                ),
            );
        }
    }


    /**
     * Read a single record based on the record ID.
     *
     * @param   integer $id     DB record ID
     * @return  boolean     True on success, False on failure
     */
    public function Read($id)
    {
        global $_TABLES;

        $id = (int)$id;
        $cache_key = self::$base_tag . ' _ ' . $id;
        $A = Cache::get($cache_key);
        if ($A === NULL) {
            $sql = "SELECT *
                    FROM {$_TABLES['shop.shipping']}
                    WHERE id = $id";
            //echo $sql;die;
            $res = DB_query($sql);
            if ($res) {
                $A = DB_fetchArray($res, false);
                Cache::set($cache_key, $A, self::$base_tag);
            }
        }
        if (!empty($A)) {
            $this->setVars($A);
            return true;
        } else {
            return false;
        }
    }


    /**
     * Set the variables from a DB record into object properties.
     *
     * @param   array   $A      Array of properties
     * @param   boolean $fromDB True if reading from DB, False if from a form
     */
    public function setVars($A, $fromDB=true)
    {
        global $LANG_SHOP;

        $this->id = SHOP_getVar($A, 'id', 'integer');
        $this->name = SHOP_getVar($A, 'name');
        $this->min_units = SHOP_getVar($A, 'min_units', 'integer');
        $this->max_units = SHOP_getVar($A, 'max_units', 'integer');
        $this->enabled = SHOP_getVar($A, 'enabled', 'integer');
        $this->use_fixed = SHOP_getVar($A, 'use_fixed', 'integer', 0);
        $this->auth_grp = SHOP_getVar($A, 'auth_grp', 'integer', 2);
        if (!$fromDB) {
            $rates = array();
            foreach ($A['rateRate'] as $id=>$txt) {
                if (empty($A['rateDscp'][$id])) {
                    $A['rateDscp'][$id] = $LANG_SHOP['shipping_type'];;
                }
                if (empty($A['rateUnits'][$id])) {
                    $A['rateUnits'][$id] = $this->max_units;
                }
                $rates[] = array(
                    'dscp' => $A['rateDscp'][$id],
                    'units' => (float)$A['rateUnits'][$id],
                    'rate' => (float)$A['rateRate'][$id],
                );
            }
            $this->rates = $rates;
            // convert valid dates to full date/time strings
            if (empty($A['valid_from'])) {
                $A['valid_from'] = self::MIN_DATETIME;
            } else {
                $A['valid_from'] = trim($A['valid_from']) . '00:00:00';
            }
            if (empty($A['valid_to'])) {
                $A['valid_to'] = self::MAX_DATETIME;
            } else {
                $A['valid_to'] = trim($A['valid_to']) . ' 23:59:59';
            }
        } else {
            $rates = json_decode($A['rates']);
            if ($rates === NULL) $rates = array();
            $this->rates = $rates;
        }
        $this->valid_from = $A['valid_from'];
        $this->valid_to = $A['valid_to'];
    }


    /**
     * Get a single shipper record.
     * Returns an empty shipper object if the requested ID is not found so
     * that object operations won't fail.
     *
     * @uses    self::getAll()
     * @param   integer $shipper_id     ID of shipper to retrieve
     * @return  object      Shipper object, new object if not found.
     */
    public static function getInstance($shipper_id)
    {
        $shippers = self::getAll(false);
        if (array_key_exists($shipper_id, $shippers)) {
            return $shippers[$shipper_id];
        } else {
            return new self;
        }
    }


    /**
     * Get all shipping options.
     *
     * @param   boolean $valid  True to get only enabled shippers
     * @return  array   Array of all DB records
     */
    public static function getAll($valid=true)
    {
        global $_TABLES, $_GROUPS;

        $cache_key = 'shippers_all_' . (int)$valid;
        $now = time();
        $shippers = Cache::get($cache_key);
        if ($shippers === NULL) {
            $shippers = array();
            $sql = "SELECT * FROM {$_TABLES['shop.shipping']}";
            if ($valid) {
                $sql .= " WHERE enabled = 1
                    AND valid_from < '$now'
                    AND valid_to > '$now'";
            }
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $shippers[$A['id']] = $A;
            }
            Cache::set($cache_key, $shippers, self::$base_tag);
        }
        $retval = array();
        foreach ($shippers as $shipper) {
            $retval[$shipper['id']] = new self($shipper);
        }
        return $retval;
    }


    /**
     * Get all the shippers that can handle a number of units.
     *
     * @param   float   $units      Number of units being shipped
     * @param   boolean $ignore_limits  True to ignore unit limit for shippers
     * @return  array               Array of shipper objects, including rates
     */
    public static function getShippers($units=0, $ignore_limits=false)
    {
        $rates = array();
        if ($units == 0) return $rates;     // no shipping, return empty

        $shippers = self::getAll();
        $shipper = new \stdClass();
        foreach ($shippers as $s_id=>&$shipper) {
            if (
                !$ignore_limits &&
                (
                    $units < $shipper->min_units ||
                    ($shipper->max_units > 0 && $units > $shipper->max_units)
                )
            ) {
                // Skip shippers that don't handle this number of units
                continue;
            } else {
                $shipper->ordershipping = new \stdClass;
                $shipper->ordershipping->packages = 0;  // not used here
                $shipper->ordershipping->total_rate = 1000000;
                foreach ($shipper->rates as $r_id=>$rate) {
                    // Calculate the shipping cost for this shipper
                    $ship_cost = $rate->rate * ceil($units / $rate->units);
                    // If the new cost is lower than the current best rate,
                    // then we found a new best rate.
                    if ($shipper->ordershipping->total_rate > $ship_cost) {
                        $shipper->ordershipping->total_rate = $ship_cost;
                    }
                }
                $rates[$s_id] = $shipper;
            }
        }
        return $rates;
    }


    /**
     * Get the single best shipper for a number of units.
     * If `$ignore_limits` is false then shippers that cannot handle the
     * number of units will be ignored. If true, then shippers will be included
     * even if they cannot ship the number of units.
     *
     * @param   integer $units      Number of units being shipped
     * @param   boolean $ignore_limits  True to ignore min and max unit limits
     * @return  object      Shipper object for the shipper with the lowest rate
     */
    public static function getBestRate($units, $ignore_limits=false)
    {
        $shippers = self::getShippers($units, $ignore_limits);
        $best = NULL;
        foreach ($shippers as $shipper) {
            if (
                $best === NULL ||
                $shipper->ordershipping->total_rate < $best->ordershipping->total_rate
            ) {
                $best = $shipper;
                $best->ordershipping->total_rate = $shipper->ordershipping->total_rate;
            }
        }
        if ($best === NULL) {
            // Create an empty object to provide zero shipping cost
            $best = new self();
        }
        return $best;
    }


    /**
     * Get all the shippers that can ship an order sorted by the total charge.
     * The shipper objects have an additional variable `ordershipping` added
     * which contains the total charge and the packages required.
     * If no qualified shippers are found, then only the total charge is
     * included and the package count is set to zero.
     *
     * @param   object  $Order  Order being shipped
     * @return  array       Array of shipper objects, with rates and packages
     */
    public static function getShippersForOrder($Order)
    {
        $cache_key = 'shipping_order_' . $Order->order_id;
        $shippers = Cache::get($cache_key);
        if (is_array($shippers)) {
            return $shippers;
        }

        // Get all the order items into a simple array where they can be
        // ordered by unit count and marked when packed.
        // This is then passed to calcBestFit() so it doesn't have to be
        // done multiple times.
        $total_units = 0;
        $fixed_shipping = 0;
        $items = array();
        foreach ($Order->getItems() as $id=>$Item) {
            $P = $Item->getProduct();
            $single_units = $P->shipping_units;
            $item_units = $single_units * $Item->quantity;
            $fixed_shipping += $P->getShipping($Item->quantity);
            $total_units += $item_units;
            for ($i = 0; $i < $Item->quantity; $i++) {
                $items[] = array(
                    'orderitem_id' => $id,
                    'item_name'     => $Item->description,
                    'single_units' => $single_units,
                    'packed'    => false,
                );
            }
        }
        // Sort items by shipping units, then reverse so larger items are
        // handled first
        usort($items, function($a, $b) {
            return $a['single_units'] <=> $b['single_units'];
        });
        $items = array_reverse($items);

        $shippers = self::getShippers($total_units);
        foreach ($shippers as $id=>&$shipper) {
            $shipper->calcBestFit($items, $total_units);
            if ($shipper->ordershipping->total_rate === NULL) {
                unset($shippers[$id]);
            }
            if ($shipper->use_fixed) {
                // Add the product fixed per-item shipping unless the shipper
                // doesn't use it.
                $shipper->ordershipping->total_rate += $fixed_shipping;
            }
        }

        // Check if at least one qualified shipper was obtainec.
        // If not, then get the best shipping rate from all shippers, ignoring
        // the max_units restriction. This is so there will be some shipping
        // charge shown.
        $active_shippers = count($shippers);
        if ($active_shippers > 0) {
            usort($shippers, function($a, $b) {
                return $a->ordershipping->total_rate <=> $b->ordershipping->total_rate;
            });
        } else {
            $shipper = self::getBestRate($Order->totalShippingUnits(), true);
            $shipper->ordershipping->total_rate += $fixed_shipping;
            if (!$shipper->isNew) {
                $shippers = array($shipper);
            } else {
                // Last resort, create a dummy shipper using the total fixed
                // shipping charge.
                $shipper = new self(array(
                    0, 1, $LANG_SHOP['shipping'], array()
                ));
                $shipper->ordershipping = new \stdClass;
                $shipper->ordershipping->total_rate = $fixed_shipping;
                $shipper->ordershipping->packages = 0;
            }
            $shippers = array($shipper);
        }

        // Cache the shippers for a short time.
        // The cache is also cleared whenever a shipper or the order is updated.
        //Cache::set($cache_key, $shippers, array('orders', self::$base_tag), 30);
        return $shippers;
    }


    /**
     * Calculate the best fit for items/packages for this shipper.
     *
     * @param   array   $items  Array of items containing basic shipping info
     * @param   float   $total_units    Total number of shipping units
     * @return  float       Shipping amount
     */
    public function calcBestFit($items, $total_units)
    {
        global $_LANG_SHOP;

        $this->ordershipping = new \stdClass;
        $this->ordershipping->total_rate = NULL;
        $this->ordershippping->packages = array();

        // Get the package types into an array to track how much space is used
        // as items are packed.
        // This should already be in ascending order by the unit count.
        // Don't bother with larger packages once a package is found that will
        // accomodate the entire shipment.
        $types = array();
        foreach ($this->rates as $type) {
            $types[] = array(
                'dscp' => $type->dscp,
                'max_units' => $type->units,
                'units_left' => $type->units,
                'rate' => $type->rate,
            );
            if ($type->units >= $total_units) {
                // If a single package will handle the entire order, then
                // there's no need to iterate through all the items.
                $this->ordershipping->packages[] = array(
                    'type' => $type->dscp,
                    'items' => array($LANG_SHOP['all']),
                    'units' => $total_units,
                    'units_left' => $type->units - $total_units,
                    'rate' => $type->rate,
                );
                $this->ordershipping->total_rate = $type->rate;
                return;
            }
        }

        // Figure out the packages that will be needed. Start with the largest item,
        $packages = array();
        $total_rate = 0;
        $units_left = $total_units;
        foreach ($items as &$item) {
            // First check to see if this item can be added to an existing package.
            foreach ($packages as &$pkg) {
                //echo "Checking {$pkg['units_left']} against item {$item['single_units']}\n";
                if ($pkg['units_left'] >= $item['single_units']) {
                    // can add more of this item to the package.
                    $pkg['items'][] = $item['item_name'];
                    $pkg['units'] += $item['single_units'];
                    $pkg['units_left'] -= $item['single_units'];
                    $item['packed'] = true;
                    break;
                }
            }
            unset($pkg);    // clear last value set in the loop

            // Couldn't fit in an existing package, create a new package.
            // Start with the largest package size, but then check to see if
            // the next-largest size is sufficient to handle the rest of the
            // shipment.
            if (!$item['packed']) {
                for ($i = count($types)-1; $i >= 0; $i--) {
                    $type = $types[$i];
                    if ($i > 0 && $types[$i-1]['max_units'] >= $units_left) {
                        // get a smaller package if it can handle the rest of the shipment.
                        //echo "skipping from {$type['dscp']} to {$nexttype['dscp']}\n";
                        continue;
                    }
                    // Check that the item will fit. If not, there's a problem.
                    if ($item['single_units'] <= $type['max_units']) {
                        $packages[] = array(
                            'type' => $type['dscp'],
                            'items' => array($item['item_name']),
                            'units' => $item['single_units'],
                            'units_left' => $type['max_units'] - $item['single_units'],
                            'rate' => $type['rate'],
                        );
                        $item['packed'] = true;
                        $total_rate += $type['rate'];
                        //echo "Created new package for " . $item['orderitem_id'] . "\n";
                        break;
                    }
                }
            }
            if ($item['packed'] !== true) {
                // This shipper cannot handle this item
                SHOP_log(__NAMESPACE__ . '\\' . __CLASS__ . "::Error packing " . print_r($item,true), SHOP_LOG_ERROR);
                break;
            } else {
                $units_left -= $item['single_units'];
            }
        }
        $this->ordershipping->total_rate = $total_rate;
        $this->ordershipping->packages = $packages;
        return;
    }


    /**
     * Set a property's value.
     *
     * @param   string  $var    Name of property to set.
     * @param   mixed   $value  New value for property.
     */
    public function __set($var, $value='')
    {
        global $_CONF;

        switch ($var) {
        case 'id':
        case 'auth_grp':
            // Integer values
            $this->properties[$var] = (int)$value;
            break;

        case 'name':
            // String values
            $this->properties[$var] = trim($value);
            break;

        case 'min_units':
            if ($value == 0) $value = self::MIN_UNITS;
        case 'max_units':
            $this->properties[$var] = (float)$value;
            break;

        case 'enabled':
        case 'use_fixed':
            $this->properties[$var] = $value == 0 ? 0 : 1;
            break;

        case 'ordershipping':
            $this->properties[$var] = $value;
            break;

        case 'valid_from':
            if (empty($value)) {
                $value = self::MIN_DATETIME;
            }
            $this->properties[$var] = new \Date($value, $_CONF['timezone']);
            break;

        case 'valid_to':
            if (empty($value)) {
                $value = self::MAX_DATETIME;
            }
            $this->properties[$var] = new \Date($value, $_CONF['timezone']);
            break;

        default:
            // Undefined values (do nothing)
            break;
        }
    }


    /**
     * Get the value of a property.
     *
     * @param   string  $var    Name of property to retrieve.
     * @return  mixed           Value of property, NULL if undefined.
     */
    public function __get($var)
    {
        if (array_key_exists($var, $this->properties)) {
            return $this->properties[$var];
        } else {
            return NULL;
        }
    }


    /**
     * Save shipper information to the database.
     *
     * @param   array   $A      Optional array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    public function Save($A =NULL)
    {
        global $_TABLES, $_SHOP_CONF;

        if (is_array($A)) {
            $this->setVars($A, false);
        }

        // Insert or update the record, as appropriate.
        if ($this->isNew) {
            $sql1 = "INSERT INTO {$_TABLES['shop.shipping']}";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['shop.shipping']}";
            $sql3 = " WHERE id={$this->id}";
        }
        usort($this->rates, function($a, $b) {
            return $a['units'] <=> $b['units'];
        });
        $sql2 = " SET name = '" . DB_escapeString($this->name) . "',
                enabled = '{$this->enabled}',
                min_units = '{$this->min_units}',
                max_units = '{$this->max_units}',
                valid_from = '{$this->valid_from->toUnix()}',
                valid_to = '{$this->valid_to->toUnix()}',
                use_fixed = '{$this->use_fixed}',
                auth_grp = '{$this->auth_grp}',
                rates = '" . DB_escapeString(json_encode($this->rates)) . "'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        DB_query($sql);
        $err = DB_error();
        if ($err == '') {
            Cache::clear(self::$base_tag);
            Cache::clear('shippers');
            return true;
        } else {
            return false;
        }
    }


    /**
     * Delete a single shipper record from the database.
     *
     * @param   integer $id     Record ID
     * @return  boolean     True on success, False on invalid ID
     */
    public static function Delete($id)
    {
        global $_TABLES;

        if ($id <= 0)
            return false;

        DB_delete($_TABLES['shop.shipping'], 'id', $id);
        Cache::clear(self::$base_tag);
        return true;
    }


    /**
     * Creates the edit form.
     *
     * @param   integer $id Attributeal ID, current record used if zero
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $_TABLES;

        $T = SHOP_getTemplate('shipping_form', 'form');
        $retval = '';
        $T->set_var(array(
            'id'            => $this->id,
            'name'          => $this->name,
            'action_url'    => SHOP_ADMIN_URL,
            'doc_url'       => SHOP_getDocURL('shipping_form',
                                            $_CONF['language']),
            'min_units'     => $this->min_units == self::MIN_UNITS ? 0 : $this->min_units,
            'max_units'     => $this->max_units,
            'ena_sel'       => $this->enabled ? 'checked="checked"' : '',
            'fixed_sel'     => $this->use_fixed ? 'checked="checked"' : '',
            'valid_from'    => $this->valid_from->format('Y-m-d', true),
            'valid_to'      => $this->valid_to->format('Y-m-d', true),
            'grp_sel'       => COM_optionList($_TABLES['groups'], 'grp_id,grp_name', $this->auth_grp),
        ) );
        $T->set_block('form', 'rateTable', 'rt');
        foreach ($this->rates as $R) {
            $T->set_var(array(
                'rate_dscp'     => $R->dscp,
                'rate_units'    => $R->units,
                'rate_price'    => Currency::getInstance()->FormatValue($R->rate),
            ) );
            $T->parse('rt', 'rateTable', true);
        }
        $retval .= $T->parse('output', 'form');
        $retval .= COM_endBlock();
        return $retval;
    }


    /**
     * Sets the "enabled" field to the opposite of the given value.
     *
     * @param   integer $oldvalue   Original field value
     * @param   integer $id         ID number of element to modify
     * @return         New value, or old value upon failure
     */
    public static function toggleEnabled($oldvalue, $id)
    {
        global $_TABLES;

        // Determing the new value (opposite the old)
        $oldvalue = $oldvalue == 0 ? 0 : 1;
        $newvalue = $oldvalue == 1 ? 0 : 1;
        $id = (int)$id;

        $sql = "UPDATE {$_TABLES['shop.shipping']}
                SET enabled = $newvalue
                WHERE id = $id";
        //echo $sql;die;
        DB_query($sql);
        if (DB_error()) {
            SHOP_log("SQL error: $sql", SHOP_LOG_ERROR);
            return $oldvalue;
        } else {
            Cache::clear(self::$base_tag);
            return $newvalue;
        }
    }


    /**
     * Displays the admin list of shippers.
     *
     * @return  string  HTML string containing the contents of the ipnlog
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM;

        $sql = "SELECT s.*, g.grp_name
            FROM {$_TABLES['shop.shipping']} s
            LEFT JOIN {$_TABLES['groups']} g
                ON g.grp_id = s.auth_grp";

        $header_arr = array(
            array(
                'text'  => 'ID',
                'field' => 'id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['enabled'],
                'field' => 'enabled',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['name'],
                'field' => 'name',
            ),
            array(
                'text'  => $LANG_SHOP['auth_grp'],
                'field' => 'grp_name',
            ),
        );

        $defsort_arr = array(
            'field' => 'name',
            'direction' => 'ASC',
        );

        $query_arr = array(
            'table' => 'shop.shipping',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => '',
        );

        $text_arr = array(
            //'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/index.php?shipping=x',
        );

        $options = array('chkdelete' => true, 'chkfield' => 'id');
        $filter = '';
        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink(
            $LANG_SHOP['new_ship_method'],
            SHOP_ADMIN_URL . '/index.php?editshipping=0',
            array('class' => 'uk-button uk-button-success')
        );
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_shiplist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the shipping profiles.
     *
     * @param  string  $fieldname  Name of field (from the array, not the db)
     * @param  mixed   $fieldvalue Value of the field
     * @param  array   $A          Array of all fields from the database
     * @param  array   $icon_arr   System icon array (not used)
     * @return string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

        static $grp_names = array();
        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                '<i class="uk-icon uk-icon-edit tooltip" title="' . $LANG_ADMIN['edit'] . '"></i>',
                SHOP_ADMIN_URL . "/index.php?editshipping={$A['id']}"
            );
            break;

        case 'enabled':
            if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                id=\"togenabled{$A['id']}\"
                onclick='SHOP_toggle(this,\"{$A['id']}\",\"enabled\",".
                "\"shipping\");' />" . LB;
            break;

        case 'delete':
            $retval .= COM_createLink(
                '<i class="uk-icon uk-icon-trash uk-text-danger"></i>',
                SHOP_ADMIN_URL. '/index.php?delshipping=x&amp;id=' . $A['id'],
                array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                )
            );
            break;

/*        case 'grp_name':
            if (!isset($grp_names[$fieldvalue])) {
                $grp_names[$fieldvalue] = DB_getItem($_TABLES['groups'], 'grp_name', "grp_id='" . $fieldvalue ."'");
            }
            $retval = $grp_names[$fieldvalue];
            break;
 */
        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }
        return $retval;
    }

}   // class Shipper

?>
