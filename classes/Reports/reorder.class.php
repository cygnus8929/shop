<?php
/**
 * Item reorder report.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.2.0
 * @since       v1.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Reports;

/**
 * Class for Order History Report.
 * @package shop
 */
class reorder extends \Shop\Report
{
    /** Icon to display on report menu
     * @var string
     */
    protected $icon = 'barcode';

    /** Item ID being reported
     * @var integer */
    private $supplier_id;

    /** Item short description for report title
     * @var string */
    private $item_dscp;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->filter_item = false;
        $this->filter_uid = false;
        $this->filter_status = false;
        $this->filter_dates = false;
        $this->extra['class'] = __CLASS__;
        parent::__construct();
    }


    /**
     * Create and render the report contents.
     *
     * @return  string  HTML for report
     */
    public function Render()
    {
        global $_TABLES, $_CONF, $LANG_SHOP;

        $this->supplier_id = SHOP_getVar($_GET, 'supplier_id');
        $T = $this->getTemplate();

        $sql = "SELECT p.id, p.name, p.short_description, p.onhand, p.reorder,
            s.company as supplier
            FROM {$_TABLES['shop.products']} p
            LEFT JOIN {$_TABLES['shop.suppliers']} s
            ON s.sup_id = p.supplier_id";

        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['item_name'],
                'field' => 'name',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['onhand'],
                'field' => 'onhand',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['reorder'],
                'field' => 'reorder',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['supplier'],
                'field' => 'supplier',
                'sort'  => true,
            ),
        );

        $defsort_arr = array(
            'field'     => 'name',
            'direction' => 'ASC',
        );

        $where = " WHERE onhand <= reorder";
        if ($this->suppliier_id > 0) {
            $where .= " AND supplier_id = " . (int)$this->supplier_id;
        }

        $query_arr = array(
            'table' => 'shop.orderstatus',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => $where,
        );

        $text_arr = array(
            'has_extras' => false,
            'form_url' => SHOP_ADMIN_URL . '/report.php?run=' . $this->key .
                '&supplier_id=' . $this->supliser_id,
            'has_limit' => true,
            'has_paging' => true,
        );

        switch ($this->type) {
        case 'html':
            $T->set_var(
                'output',
                \ADMIN_list(
                    'shop_rep_reorder',
                    array('\Shop\Report', 'getReportField'),
                    $header_arr, $text_arr, $query_arr, $defsort_arr, '', $this->extra
                )
            );
            break;
        case 'csv':
            $sql .= ' ' . $query_arr['default_filter'];
            $res = DB_query($sql);
            $T->set_block('report', 'ItemRow', 'row');
            while ($A = DB_fetchArray($res, false)) {
                $T->set_var(array(
                    'item_name'     => $A['name'],
                    'dscp'          => $this->remQuote($A['short_description']),
                    'onhand'        => $A['onhand'],
                    'reorder'       => $A['reorder'],
                    'supplier'      => $A['supplier'],
                    'nl'            => "\n",
                ) );
                $T->parse('row', 'ItemRow', true);
            }
            break;
        }

        $T->set_var(array(
            'report_key' => $this->key,
            'item_id'   => $this->item_id,
            'item_dscp' => $this->item_dscp,
            'startDate' => $this->startDate->format($_CONF['shortdate'], true),
            'endDate'   => $this->endDate->format($_CONF['shortdate'], true),
            'nl'        => "\n",
        ) );
        $T->parse('output', 'report');
        $report = $T->finish($T->get_var('output'));
        return $this->getOutput($report);
    }


    /**
     * Get the display value for a field specific to this report.
     * This function takes over the "default" handler in Report::getReportField().
     * @access  protected as it is only called from Report::getReportField().
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @param   array   $extra      Extra verbatim values
     * @return  string              HTML for field display in the table
     */
    protected static function fieldFunc($fieldname, $fieldvalue, $A, $icon_arr, $extra)
    {
        switch ($fieldname) {
        case 'onhand':
        case 'reorder':
            $retval = (float)$fieldvalue;
            break;
        }
        return $retval;
    }

}

?>
