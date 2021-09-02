<?php
namespace App\Utils;

use Illuminate\Support\Facades\DB;

use Spatie\Permission\Models\Role;

use App\Transaction;
use App\BusinessLocation;
use App\User;
use Session;
use Illuminate\Support\Facades\Auth;
class RestaurantUtil
{
    /**
     * Retrieves all orders/sales
     *
     * @param int $business_id
     * @param array $filter
     * *For new orders order_status is 'received'
     *
     * @return obj $orders
     */
    public function getAllOrders($business_id, $filter = [])
    {
        $user_id=Session::get('user.id');
        $details= DB::table('users')->where('id',$user_id)->first();
        $query = Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
                ->leftjoin(
                    'business_locations AS bl',
                    'transactions.location_id',
                    '=',
                    'bl.id'
                )
                ->leftjoin(
                    'res_tables AS rt',
                    'transactions.res_table_id',
                    '=',
                    'rt.id'
                )
                ->where('transactions.business_id', $business_id)
                ->where('transactions.type', 'sell')
                ->where('transactions.status', 'final');
        // if($details->service_center !=null){
        //     $query=$query->where('transactions.service_center',$details->service_center);
        // }

        //For new orders order_status is 'received'
        if (!empty($filter['order_status']) && $filter['order_status'] == 'received') {
            $query->whereNull('res_order_status');
        }

        if (!empty($filter['waiter_id'])) {
            $query->where('transactions.res_waiter_id', $filter['waiter_id']);
        }
                
        $orders =  $query->select(
            'transactions.*',
            'contacts.name as customer_name',
            'bl.name as business_location',
            'rt.name as table_name'
        )
                ->orderBy('created_at', 'desc')
                ->get();

        return $orders;
    }


    public function showAllOrders($business_id, $filter = [])
    {
        $user_id=Session::get('user.id');
        
        $details= DB::table('users')->where('id',$user_id)->first();
        // dd( $details->service_center);
        $query = Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
                ->leftjoin(
                    'business_locations AS bl',
                    'transactions.location_id',
                    '=',
                    'bl.id'
                )
                ->leftjoin(
                    'res_tables AS rt',
                    'transactions.res_table_id',
                    '=',
                    'rt.id'
                )
                ->where('transactions.business_id', $business_id)
                ->where('transactions.type', 'sell')
                ->where('transactions.status', 'final');

        //For new orders order_status is 'received'
        if (!empty($filter['order_status']) && $filter['order_status'] == 'received') {
            $query->whereNull('res_order_status');
        }

       if(!empty( $details->service_center))
       {
        $query->where('res_order_status') ->where('transactions.service_center',$details->service_center);
       }
                
        $orders =  $query->select(
            'transactions.*',
            'contacts.name as customer_name',
            'bl.name as business_location',
            'rt.name as table_name'
        )
                ->orderBy('created_at', 'desc')
                ->get();

        return $orders;
    }

    public function service_staff_dropdown($business_id)
    {
        //Get all service staff roles
        $service_staff_roles = Role::where('business_id', $business_id)
                                ->where('is_service_staff', 1)
                                ->get()
                                ->pluck('name')
                                ->toArray();

        $service_staff = [];

        //Get all users of service staff roles
        if (!empty($service_staff_roles)) {
            $service_staff = User::where('business_id', $business_id)->role($service_staff_roles)->get()->pluck('first_name', 'id');
        }

        return $service_staff;
    }

    public function is_service_staff($user_id)
    {
        $is_service_staff = false;
        $user = User::find($user_id);
        if ($user->roles->first()->is_service_staff == 1) {
            $is_service_staff = true;
        }

        return $is_service_staff;
    }
    
    
    // new update for track user  update or delete of sell

    public function insertUpdateDeleteTransactionExistingData($transaction_id,$action_type)
    {
        $oldData =  \App\Transaction::findOrFail($transaction_id);
        $this->insertUpdateDeleteTransactionSellLineExistingData($transaction_id,$action_type);
         if($oldData->payment_status == "paid")
         {
            $this->insertUpdateDeleteTransactionPaymentExistingData($transaction_id,$action_type);     
         }
         
        $data = new \App\Transaction_update_delete_history();
        $data->transaction_id = $transaction_id;
        $data->action_by = Auth::user()->id;
        $data->action_type = $action_type;
        $data->business_id = $oldData->business_id;
        $data->location_id = $oldData->location_id;
        $data->res_table_id = $oldData->res_table_id;
        $data->res_waiter_id = $oldData->res_waiter_id;
        $data->res_order_status = $oldData->res_order_status;
        $data->type = $oldData->type;
        $data->status = $oldData->status;
        $data->is_quotation = $oldData->is_quotation;
        $data->payment_status = $oldData->payment_status;
        $data->adjustment_type = $oldData->adjustment_type;
        $data->contact_id = $oldData->contact_id;
        $data->customer_group_id = $oldData->customer_group_id;
        $data->invoice_no = $oldData->invoice_no;
        $data->ref_no = $oldData->ref_no;
        $data->transaction_date = $oldData->transaction_date;
        $data->total_before_tax = $oldData->total_before_tax;
        $data->tax_id = $oldData->tax_id;
        $data->tax_amount = $oldData->tax_amount;
        $data->discount_type = $oldData->discount_type;
        $data->discount_amount = $oldData->discount_amount;
        $data->shipping_details = $oldData->shipping_details;
        $data->shipping_charges = $oldData->shipping_charges;
        $data->additional_notes = $oldData->additional_notes;
        $data->staff_note = $oldData->staff_note;
        $data->final_total = $oldData->final_total;
        $data->expense_category_id = $oldData->expense_category_id;
        $data->expense_for = $oldData->expense_for;
        $data->commission_agent = $oldData->commission_agent;
        $data->document = $oldData->document;
        $data->is_direct_sale = $oldData->is_direct_sale;
        $data->is_suspend = $oldData->is_suspend;
        $data->exchange_rate = $oldData->exchange_rate;
        $data->total_amount_recovered = $oldData->total_amount_recovered;
        $data->transfer_parent_id = $oldData->transfer_parent_id;
        $data->return_parent_id = $oldData->return_parent_id;
        $data->opening_stock_product_id = $oldData->opening_stock_product_id;
        $data->created_by = $oldData->created_by;
        $data->pay_term_number = $oldData->pay_term_number;
        $data->pay_term_type = $oldData->pay_term_type;
        $data->woocommerce_order_id = $oldData->woocommerce_order_id;
        $data->selling_price_group_id = $oldData->selling_price_group_id;
        $data->supplier_id = $oldData->supplier_id;
        $data->sale_created_date = $oldData->created_at;
        $data->sale_updated_date = $oldData->updated_at;
        $data->save();
        return $data;
    }


    public function insertUpdateDeleteTransactionSellLineExistingData($transaction_id,$action_type)
    {
            $oldData = \App\TransactionSellLine::where('transaction_id',$transaction_id)->first();
            $data = new \App\Transaction_sell_line_update_delete_history();
            $data->action_type = $action_type;
            $data->action_by = Auth::user()->id;
            $data->transaction_id = $oldData->transaction_id;
            $data->product_id = $oldData->product_id;
            $data->variation_id = $oldData->variation_id;
            $data->quantity = $oldData->quantity;
            $data->quantity_returned = $oldData->quantity_returned;
            $data->unit_price_before_discount = $oldData->unit_price_before_discount;
            $data->unit_price = $oldData->unit_price;
            $data->mrp_price = $oldData->mrp_price;
            $data->line_discount_type = $oldData->line_discount_type;
            $data->line_discount_amount = $oldData->line_discount_amount;
            $data->unit_price_inc_tax = $oldData->unit_price_inc_tax;
            $data->item_tax = $oldData->item_tax;
            $data->tax_id = $oldData->tax_id;
            $data->lot_no_line_id = $oldData->lot_no_line_id;
            $data->sell_line_note = $oldData->sell_line_note;
            $data->woocommerce_line_items_id = $oldData->woocommerce_line_items_id;
            $data->sell_created_date = $oldData->created_at;
            $data->sell_updated_date = $oldData->updated_at;
            $data->save();
            return $data;
    }
    
    
    public function insertUpdateDeleteTransactionPaymentExistingData($transaction_id,$action_type)
    {
            $oldData = \App\TransactionPayment::where('transaction_id',$transaction_id)->first();
            
            $data = new \App\Transaction_payment_update_delete_history();
             $data->transaction_id = $oldData->transaction_id;
             $data->action_type = $action_type;
             $data->action_by = Auth::user()->id;
             $data->business_id = $oldData->business_id;
             $data->is_return = $oldData->is_return;
             $data->amount = $oldData->amount;
             $data->method = $oldData->method;
             $data->transaction_no = $oldData->transaction_no;
             $data->card_transaction_number = $oldData->card_transaction_number;
             $data->bank_name = $oldData->bank_name;
             $data->tax_amount = $oldData->tax_amount;
             $data->remain_amount = $oldData->remain_amount;
             $data->card_number = $oldData->card_number;
             $data->card_type = $oldData->card_type;
             $data->card_holder_name = $oldData->card_holder_name;
             $data->card_month = $oldData->card_month;
             $data->card_year = $oldData->card_year;
             $data->card_security = $oldData->card_security;
             $data->cheque_number = $oldData->cheque_number;
             $data->bank_account_number = $oldData->bank_account_number;
             $data->paid_on = $oldData->paid_on;
             $data->created_by = $oldData->created_by;
             $data->payment_for = $oldData->payment_for;
             $data->parent_id = $oldData->parent_id;
             $data->note = $oldData->note;
             $data->payment_ref_no = $oldData->payment_ref_no;
             $data->account_id = $oldData->account_id;
             $data->payment_created_date = $oldData->created_at;
             $data->payment_updated_date = $oldData->updated_at;
             $data->save();
             return $data;
    }
}
