<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;

use App\Product;
use App\Variation;
use App\TaxRate;
use App\ProductVariation;
use App\Business;
use App\Transaction;
use App\VariationLocationDetails;
use App\ProductRack;
use App\ServiceStock; #moinul
use App\VariationGroupPrice;
use App\VariationTemplate;
use App\VariationValueTemplate;
use App\BusinessLocation;
use App\PurchaseLine;
use App\Drink;
use Redirect;

class ProductUtil extends Util
{

    /**
     * Returns the list of barcode types
     *
     * @return array
     */
    public function barcode_types()
    {
        $types = [ 'C128' => 'Code 128 (C128)', 'C39' => 'Code 39 (C39)', 'EAN13' => 'EAN-13', 'EAN8' => 'EAN-8', 'UPCA' => 'UPC-A', 'UPCE' => 'UPC-E'];

        return $types;
    }

    /**
     * Returns the default barcode.
     *
     * @return string
     */
    public function barcode_default()
    {
        return 'C128';
    }

    /**
     * Create single type product variation
     *
     * @param (int or object) $product
     * @param $sku
     * @param $purchase_price
     * @param $dpp_inc_tax (default purchase pric including tax)
     * @param $profit_percent
     * @param $selling_price
     *
     * @return boolean
     */
    public function createSingleProductVariation($product, $sku, $purchase_price, $dpp_inc_tax, $profit_percent, $selling_price, $selling_price_inc_tax,$sd_single_dsp,$sd_single_dsp_inc_tax)
    {
        if (!is_object($product)) {
            $product = Product::find($product);
        }

        //create product variations
        $product_variation_data = [
                                    'name' => 'DUMMY',
                                    'is_dummy' => 1
                                ];
        $product_variation = $product->product_variations()->create($product_variation_data);
                
        //create variations
        $variation_data = [
                'name' => 'DUMMY',
                'product_id' => $product->id,
                'sub_sku' => $sku,
                'default_purchase_price' => $this->num_uf($purchase_price),
                'dpp_inc_tax' => $this->num_uf($dpp_inc_tax),
                'profit_percent' => $this->num_uf($profit_percent),
                'default_sell_price' => $this->num_uf($selling_price),
                'sell_price_inc_tax' => $this->num_uf($selling_price_inc_tax),
                'sd_single_dsp' => $this->num_uf($sd_single_dsp),
                'sd_single_dsp_inc_tax' => $this->num_uf($sd_single_dsp_inc_tax)
            ];
        $product_variation->variations()->create($variation_data);

        return true;
    }

    /**
     * Create variable type product variation
     *
     * @param (int or object) $product
     * @param $input_variations
     *
     * @return boolean
     */
    public function createVariableProductVariations($product, $input_variations, $business_id = null)
    {
        if (!is_object($product)) {
            $product = Product::find($product);
        }

        //create product variations
        foreach ($input_variations as $key => $value) {
            $variation_template_name = !empty($value['name']) ? $value['name'] : null;
            $variation_template_id = !empty($value['variation_template_id']) ? $value['variation_template_id'] : null;

            if (empty($variation_template_id)) {
                if ($variation_template_name != 'DUMMY') {
                    $variation_template = VariationTemplate::where('business_id', $business_id)
                                                        ->whereRaw('LOWER(name)="' . strtolower($variation_template_name) . '"')
                                                        ->with(['values'])
                                                        ->first();
                    if (empty($variation_template)) {
                        $variation_template = VariationTemplate::create([
                            'name' => $variation_template_name,
                            'business_id' => $business_id
                        ]);
                    }
                    $variation_template_id = $variation_template->id;
                }
            } else {
                $variation_template = VariationTemplate::with(['values'])->find($value['variation_template_id']);
                $variation_template_id = $variation_template->id;
                $variation_template_name = $variation_template->name;
            }

            $product_variation_data = [
                                    'name' => $variation_template_name,
                                    'product_id' => $product->id,
                                    'is_dummy' => 0,
                                    'variation_template_id' => $variation_template_id
                                ];
            $product_variation = ProductVariation::create($product_variation_data);
            
            //create variations
            if (!empty($value['variations'])) {
                $variation_data = [];

                $c = Variation::withTrashed()
                        ->where('product_id', $product->id)
                        ->count() + 1;
                
                foreach ($value['variations'] as $k => $v) {
                    $sub_sku = empty($v['sub_sku'])? $this->generateSubSku($product->sku, $c, $product->barcode_type) :$v['sub_sku'];
                    $variation_value_id = !empty($v['variation_value_id']) ? $v['variation_value_id'] : null;
                    $variation_value_name = !empty($v['value']) ? $v['value'] : null;

                    if (!empty($variation_value_id)) {
                        $variation_value = $variation_template->values->filter(function ($item) use ($variation_value_id) {
                            return $item->id == $variation_value_id;
                        })->first();
                        $variation_value_name = $variation_value->name;
                    } else {
                        if (!empty($variation_template)) {
                            $variation_value =  VariationValueTemplate::where('variation_template_id', $variation_template->id)
                                ->whereRaw('LOWER(name)="' . $variation_value_name . '"')
                                ->first();
                            if (empty($variation_value)) {
                                $variation_value =  VariationValueTemplate::create([
                                    'name' => $variation_value_name,
                                    'variation_template_id' => $variation_template->id
                                ]);
                            }
                            $variation_value_id = $variation_value->id;
                            $variation_value_name = $variation_value->name;
                        } else {
                            $variation_value_id = null;
                            $variation_value_name = $variation_value_name;
                        }
                    }

                    $variation_data[] = [
                      'name' => $variation_value_name,
                      'variation_value_id' => $variation_value_id,
                      'product_id' => $product->id,
                      'sub_sku' => $sub_sku,
                      'default_purchase_price' => $this->num_uf($v['default_purchase_price']),
                      'dpp_inc_tax' => $this->num_uf($v['dpp_inc_tax']),
                      'profit_percent' => $this->num_uf($v['profit_percent']),
                      'default_sell_price' => $this->num_uf($v['default_sell_price']),
                      'sell_price_inc_tax' => $this->num_uf($v['sell_price_inc_tax'])
                    ];
                    $c++;
                }
                $product_variation->variations()->createMany($variation_data);
            }
        }
    }

    /**
     * Update variable type product variation
     *
     * @param $product_id
     * @param $input_variations_edit
     *
     * @return boolean
     */
    public function updateVariableProductVariations($product_id, $input_variations_edit)
    {
        $product = Product::find($product_id);

        //Update product variations
        $product_variation_ids = [];
        foreach ($input_variations_edit as $key => $value) {
            $product_variation_ids[] = $key;

            $product_variation = ProductVariation::find($key);
            $product_variation->name = $value['name'];
            $product_variation->save();

            //Update existing variations
            $variations_ids = [];
            if (!empty($value['variations_edit'])) {
                foreach ($value['variations_edit'] as $k => $v) {
                    $data = [
                        'name' => $v['value'],
                        'default_purchase_price' => $this->num_uf($v['default_purchase_price']),
                        'dpp_inc_tax' => $this->num_uf($v['dpp_inc_tax']),
                        'profit_percent' => $this->num_uf($v['profit_percent']),
                        'default_sell_price' => $this->num_uf($v['default_sell_price']),
                        'sell_price_inc_tax' => $this->num_uf($v['sell_price_inc_tax'])
                    ];
                    if (!empty($v['sub_sku'])) {
                        $data['sub_sku'] = $v['sub_sku'];
                    }
                    Variation::where('id', $k)
                        ->where('product_variation_id', $key)
                        ->update($data);

                    $variations_ids[] = $k;
                }
            }
            Variation::whereNotIn('id', $variations_ids)
                    ->where('product_variation_id', $key)
                    ->delete();

            //Add new variations
            if (!empty($value['variations'])) {
                $variation_data = [];
                $c = Variation::withTrashed()
                                ->where('product_id', $product->id)
                                ->count()+1;

                foreach ($value['variations'] as $k => $v) {
                    $sub_sku = empty($v['sub_sku'])? $this->generateSubSku($product->sku, $c, $product->barcode_type) :$v['sub_sku'];

                    $variation_value_name = !empty($v['value'])? $v['value'] : null;
                    $variation_value_id = null;

                    if (!empty($product_variation->variation_template_id)) {
                        $variation_value =  VariationValueTemplate::where('variation_template_id', $product_variation->variation_template_id)
                                ->whereRaw('LOWER(name)="' . $v['value'] . '"')
                                ->first();
                        if (empty($variation_value)) {
                            $variation_value =  VariationValueTemplate::create([
                                'name' => $v['value'],
                                'variation_template_id' => $product_variation->variation_template_id
                            ]);
                        }
                        
                        $variation_value_id = $variation_value->id;
                    }

                    $variation_data[] = [
                      'name' => $variation_value_name,
                      'variation_value_id' => $variation_value_id,
                      'product_id' => $product->id,
                      'sub_sku' => $sub_sku,
                      'default_purchase_price' => $this->num_uf($v['default_purchase_price']),
                      'dpp_inc_tax' => $this->num_uf($v['dpp_inc_tax']),
                      'profit_percent' => $this->num_uf($v['profit_percent']),
                      'default_sell_price' => $this->num_uf($v['default_sell_price']),
                      'sell_price_inc_tax' => $this->num_uf($v['sell_price_inc_tax'])
                    ];
                    $c++;
                }
                $product_variation->variations()->createMany($variation_data);
            }
        }

        ProductVariation::where('product_id', $product_id)
                ->whereNotIn('id', $product_variation_ids)
                ->delete();
    }

    /**
     * Checks if products has manage stock enabled then Updates quantity for product and its
     * variations
     *
     * @param $location_id
     * @param $product_id
     * @param $variation_id
     * @param $new_quantity
     * @param $old_quantity = 0
     * @param $number_format = null
     *
     * @return boolean
     */
    public function updateProductQuantity($location_id, $product_id, $variation_id, $new_quantity, $old_quantity = 0, $number_format = null)
    {
        
        
        $qty_difference = $this->num_uf($new_quantity, $number_format) - $this->num_uf($old_quantity, $number_format);

        $product = Product::find($product_id);

        //Check if stock is enabled or not.
        if ($product->enable_stock == 1 && $qty_difference != 0) {
            $variation = Variation::where('id', $variation_id)
                            ->where('product_id', $product_id)
                            ->first();
            
            //Add quantity in VariationLocationDetails
            $variation_location_d = VariationLocationDetails
                                    ::where('variation_id', $variation->id)
                                    ->where('product_id', $product_id)
                                    ->where('product_variation_id', $variation->product_variation_id)
                                    ->where('location_id', $location_id)
                                    ->first();

            if (empty($variation_location_d)) {
                $variation_location_d = new VariationLocationDetails();
                $variation_location_d->variation_id = $variation->id;
                $variation_location_d->product_id = $product_id;
                $variation_location_d->location_id = $location_id;
                $variation_location_d->product_variation_id = $variation->product_variation_id;
                $variation_location_d->qty_available = 0;
            }

            $variation_location_d->qty_available += $qty_difference;
            $variation_location_d->save();

            //TODO: Add quantity in products table
            // Product::where('id', $product_id)
            //     ->increment('total_qty_available', $qty_difference);
        }
        
        return true;
    }

    public function updateProductQuantity2($location_id, $product_id, $variation_id, $new_quantity, $old_quantity = 0, $number_format = null)
    {
        $qty_difference = $this->num_uf($new_quantity, $number_format) - $this->num_uf($old_quantity, $number_format);

        $product = Product::find($product_id);

        //Check if stock is enabled or not.
        if ($product->enable_stock == 1 && $qty_difference != 0) {
            $variation = Variation::where('id', $variation_id)
                            ->where('product_id', $product_id)
                            ->first();
            
            //Add quantity in VariationLocationDetails
            $variation_location_d = VariationLocationDetails
                                    ::where('variation_id', $variation->id)
                                    ->where('product_id', $product_id)
                                    ->where('product_variation_id', $variation->product_variation_id)
                                    ->where('location_id', $location_id)
                                    ->first();

            if (empty($variation_location_d)) {
                $variation_location_d = new VariationLocationDetails();
                $variation_location_d->variation_id = $variation->id;
                $variation_location_d->product_id = $product_id;
                $variation_location_d->location_id = $location_id;
                $variation_location_d->product_variation_id = $variation->product_variation_id;
                $variation_location_d->qty_available = 0;
            }

            $variation_location_d->qty_available += $qty_difference;
            $variation_location_d->save();

            //TODO: Add quantity in products table
            // Product::where('id', $product_id)
            //     ->increment('total_qty_available', $qty_difference);
        }
        
        return true;
    }

    /**
     * Checks if products has manage stock enabled then Decrease quantity for product and its variations
     *
     * @param $product_id
     * @param $variation_id
     * @param $location_id
     * @param $new_quantity
     * @param $old_quantity = 0
     *
     * @return boolean
     */

     public function decreaseProductQuantity3($product_id, $variation_id, $location_id, $new_quantity,$type, $old_quantity = 0){
        $qty_difference = $new_quantity - $old_quantity;
        $product = Product::find($product_id);
        if ($product->enable_stock == 1) {

            if($type=='a'){
              VariationLocationDetails::where('variation_id', $variation_id)
                ->where('product_id', $product_id)
                ->where('location_id', $location_id)
                ->increment('qty_available', $qty_difference);
              }else{
                VariationLocationDetails::where('variation_id', $variation_id)
                ->where('product_id', $product_id)
                ->where('location_id', $location_id)
                ->decrement('qty_available', $qty_difference);
              }
            
        }

        return true;
    }

    public function decreaseProductQuantity2($product_id, $variation_id, $location_id, $new_quantity, $old_quantity = 0){
        $qty_difference = $new_quantity - $old_quantity;
        $product = Product::find($product_id);
        if ($product->enable_stock == 1) {
            VariationLocationDetails::where('variation_id', $variation_id)
                ->where('product_id', $product_id)
                ->where('location_id', $location_id)
                ->decrement('qty_available', $qty_difference);
        }

        return true;
    }


    public function decreaseProductQuantity($product_id, $variation_id, $location_id,$service_center, $new_quantity,$transaction=null, $old_quantity = 0)
    {

        $qty_difference = $new_quantity - $old_quantity;
        $product = Product::find($product_id);
        if ($product->dish_type ==3){
           $checkInDishList =\App\Kitchendishlist::with('raws','raws.product','raws.unit')
                              ->where('product_id',$product_id)
                              ->first();
            foreach($checkInDishList->raws as $checkInDish){
                    $qty = $checkInDish->used_quantity;

                    if($checkInDish->used_unit_id != '0'){

                        if($checkInDish->unit->child_value){

                            $qty = $checkInDish->used_quantity/$checkInDish->unit->child_value;
                        }else{
                            $qty = $checkInDish->used_quantity;
                        }
                       
                    }

                        DB::table('variation_location_details')
                        ->where('product_id',$checkInDish->raw_item_id)
                        ->where('location_id',1)
                        ->decrement('qty_available',$qty*$qty_difference);                                
                }
        }
        # product item is cocktel type
        else{
          $service=($product->dish_type==13)? 1: $service_center;
          $rowItem =Drink::with('raws','raws.unit')
                          ->where('drinks.product_id', $product_id)
                          ->where('drinks.service_center_id', $service)
                          ->first();

                foreach($rowItem->raws as $row)
                {
                  $qty = $row->used_quantity;
                  if($row->used_unit_id != '0')
                  {
                      if($row->unit->child_value){
                          $qty = $row->used_quantity/$row->unit->child_value;
                      }else{
                          $qty = $row->used_quantity;
                      }
                  }
                      DB::Table('service_stock_management')
                      ->where(['product_id'=>$row->raw_product_id,'service_center_id'=>$service])
                      ->decrement('remain_stock',$qty*$qty_difference);
                      // here insert raw sell track
                }
        }
        return true;
    }



   #--------------working----------------------
    public function checkQuantity($product_id, $variation_id, $location_id,$service_center=null)
    {
        $product= Product::find($product_id);

            #if product desh)type == 3 == food and others item, then...
            if($product->dish_type == 3)
            {
                $min_checker = 0;
                $dish = DB::table('kitchen_dish_list')
                        ->where('product_id', $product_id) #317
                        ->first();

                # kitchen_dish_list->id 
                $used_items = DB::table('kitchen_used_raw_items')
                                ->where('dish_id', $dish->id) # kitchen_dish_list->id 
                                ->get();

                if(isset($used_items))
                {
                  foreach($used_items as $items)
                  {
                      $stock_check = DB::table('variation_location_details')
                          ->select(DB::raw("SUM(qty_available) as remain_stock"))
                          ->where('location_id',1)
                          ->where('product_id', $items->raw_item_id) 
                          ->first();
                    if($items->used_unit_id != '0')
                    {
                        $unit = DB::table('units')
                                  ->where('id',$items->used_unit_id)
                                  ->first();
                        if($unit->child_value)
                        {
                            $qty = $items->used_quantity/$unit->child_value;
                        }
                        else{
                            $qty = $items->used_quantity;
                        }
                    }
                    $temp = $stock_check->remain_stock / $qty;
                    // $product_id[]=$items->raw_item_id;
                    $array[]=$temp;
                    $value=min($array);
                    $min_checker = floor($value);
                }
              }
              return $min_checker;
            }
            #-----------------------------------------------------------------
            ##if product is cocktail item,then...
            elseif($product->dish_type == 13)
            {
                  $stock=0;
                  $rowItem= DB::table('drink_raw_items')
                            ->leftjoin('drinks','drinks.id','=','drink_raw_items.drink_id')
                            ->where('drinks.product_id', $product_id)
                            //->where('drinks.service_center_id', $service_center)
                            ->where('drinks.service_center_id', 1) #moinul
                            ->get();

                     if($rowItem[0]->raw_product_id)
                     {
                        foreach($rowItem as $row)
                        {
                          $stock_check = DB::table('service_stock_management')
                                    ->select(DB::raw("SUM(remain_stock) as remain_stock"),
                                        DB::raw("SUM(adjust_stock) as adjust_stock"),
                                      
                                        'service_stock_management.*')
                                      ->where('product_id', $row->raw_product_id)
                                      //->where('service_center_id',$row->service_center_id)
                                      ->where('service_center_id',1) #moinul
                                      ->first();
                                       // DB::raw("SUM(remain_stock + adjust_stock) as total_remain_stock"),
                          $qty = $row->used_quantity;

                          if($row->used_unit_id != '0')
                          {
                              $unit = DB::table('units')
                                      ->where('id',$row->used_unit_id)
                                      ->first();
                              if($unit->child_value){
                                  $qty = $row->used_quantity/$unit->child_value;
                              }else{
                                  $qty = $row->used_quantity;
                              }
                          }
                     
                          $get_stock=$stock_check->remain_stock / $qty;
                          $array[]=$get_stock;
                          $value=min($array);
                          $stock = $value;
                 
                          ### for stock check from adjust_stock field
                         /*
                          $get_stock=$stock_check->total_remain_stock / $qty;
                          ### for stock check from adjust_stock field
                          $array[]=$get_stock;
                          $value=min($array);
                          $stock = $value;
                         */
                        
                        }  
                     }else{
                       $stock=0;
                     }
                  return $stock;
            }
            #-----------------------------------------------------------------
            else{
                  $stock=0;
                  $rowItem= DB::table('drink_raw_items')
                            ->leftjoin('drinks','drinks.id','=','drink_raw_items.drink_id')
                            ->where('drinks.product_id', $product_id)
                            ->where('drinks.service_center_id', $service_center)
                            ->get();

                     if($rowItem[0]->raw_product_id)
                     {
                        foreach($rowItem as $row)
                        {
                          $stock_check = DB::table('service_stock_management')
                                    ->select(DB::raw("SUM(remain_stock) as remain_stock"),
                                        DB::raw("SUM(adjust_stock) as adjust_stock"),
                                    
                                        'service_stock_management.*')
                                      ->where('product_id', $row->raw_product_id)
                                      ->where('service_center_id',$row->service_center_id)
                                      ->first();
                                      //DB::raw("SUM(remain_stock + adjust_stock) as total_remain_stock"),
                          $qty = $row->used_quantity;

                          if($row->used_unit_id != '0')
                          {
                              $unit = DB::table('units')
                                      ->where('id',$row->used_unit_id)
                                      ->first();
                              if($unit->child_value){
                                  $qty = $row->used_quantity/$unit->child_value;
                              }else{
                                  $qty = $row->used_quantity;
                              }
                          }

                        
                        
                          $get_stock=$stock_check->remain_stock / $qty;
                          $array[]=$get_stock;
                          $value=min($array);
                          $stock = $value;
                        
                         
                         /*
                          ### for stock check from adjust_stock field
                          $get_stock = $stock_check->total_remain_stock / $qty;
                          ### for stock check from adjust_stock field

                          $array[]=$get_stock;
                          $value=min($array);
                          $stock = $value;
                         */
                        }  
                     }else{
                       $stock=0;
                     }
                  return $stock;
            }
    }
  #--------------working----------------------
    
    // public function decreaseProductQuantity($service_center_id=null,$product_id, $variation_id, $location_id, $new_quantity, $old_quantity = 0)
    // {
    //   $service_center=$service_center_id;
    //     $qty_difference = $new_quantity - $old_quantity;
       
    //     $product = Product::find($product_id);

    //     // quantity difference for pack
    //    $unit=DB::table('units')
    //         ->where('id', $product->unit_id)
    //         ->first();
           
    //     // if( $unit->actual_name=="pack")
    //     // {
    //     //     $qty_difference= ($qty_difference*43)/1000;
           
    //     // }


    //     //Check if stock is enabled or not.
    //     if ($product->enable_stock == 1) {
    //         //Decrement Quantity in variations location table
            
    //           //jack do something to ServiceStock management
    //           if($service_center!="0")
    //           {
    //               $update_stock=DB::table('service_stock_management')
    //                                ->where('service_center_id',$service_center)
    //                                ->where('product_id',$product_id)
    //                                ->first();

    //                                if( $update_stock)
    //                                {
    //                                 $update_stock->remain_stock= $update_stock->remain_stock-$qty_difference;
    //                                 $update_stock=DB::table('service_stock_management')
    //                                               ->where('service_center_id',$service_center)
    //                                               ->where('product_id',$product_id)
    //                                               ->update(['remain_stock'=>$update_stock->remain_stock]);
    //                                }
    //                                else
    //                                {
    //                                 VariationLocationDetails::where('variation_id', $variation_id)
    //                                 ->where('product_id', $product_id)
    //                                 ->where('location_id', $location_id)
    //                                 ->decrement('qty_available', $qty_difference);
    //                                }                                                       
    //           }

           
    //         else{
    //             VariationLocationDetails::where('variation_id', $variation_id)
    //             ->where('product_id', $product_id)
    //             ->where('location_id', $location_id)
    //             ->decrement('qty_available', $qty_difference);
    //         }
            
             
            
    //         // Variation::where('id', $variation_id)
    //         //     ->where('product_id', $product_id)
    //         //     ->decrement('qty_available', $qty_difference);

    //         //TODO: Decrement quantity in products table
    //         // Product::where('id', $product_id)
    //         //     ->decrement('total_qty_available', $qty_difference);
    //     }

    //     return true;
    // }

    /**
     * Get all details for a product from its variation id
     *
     * @param int $variation_id
     * @param int $business_id
     * @param int $location_id
     * @param bool $check_qty (If false qty_available is not checked)
     *
     * @return array
     */
    public function checkRecipeQty($product_id, $service_center,$location_id = null)
    {

        $product = Product::find($product_id);
        $min_checker=0;
        if ($product->dish_type ==3){
           $checkInDishList =\App\Kitchendishlist::with('raws','raws.product','raws.unit')
                              ->where('product_id',$product_id)
                              ->first();

            foreach($checkInDishList->raws as $checkInDish){

                  $stock_check = DB::table('variation_location_details')
                                ->select(DB::raw("SUM(qty_available) as remain_stock"))
                                ->where('location_id',$location_id)
                                ->where('product_id', $checkInDish->raw_item_id)
                                ->first();

                    if($checkInDish->used_unit_id != '0'){

                        if($checkInDish->unit->child_value){

                            $qty = $checkInDish->used_quantity/$checkInDish->unit->child_value;
                        }else{
                            $qty = $checkInDish->used_quantity;
                        }
                       
                    }
                  $temp = $stock_check->remain_stock / $qty;
                  $array[]=$temp;
                  $value=min($array);
                  $min_checker = $value;

                      
          }
      }
        
      else{
          $rowItem =Drink::with('raws','raws.unit')
                          ->where('drinks.product_id', $product_id)
                          ->where('drinks.service_center_id', $service_center)
                          ->first();

  
            foreach($rowItem->raws as $row)
            {
              $stock_check = DB::table('service_stock_management')
                                    ->select(DB::raw("SUM(remain_stock) as remain_stock"),
                                        DB::raw("SUM(adjust_stock) as adjust_stock"),
                                        'service_stock_management.*')
                                    ->where('product_id', $row->raw_product_id)
                                    ->where('service_center_id',$service_center)
                                    ->first();

                  if($row->used_unit_id != '0')
                  {
                      if($row->unit->child_value){
                          $qty = $row->used_quantity/$row->unit->child_value;
                      }else{
                          $qty = $row->used_quantity;
                      }
                  }
                $get_stock=$stock_check->remain_stock / $qty;
                          $array[]=$get_stock;
                          $value=min($array);
                          $min_checker = $value;

          }
      }

      if ($min_checker==0) {
          throw new \ErrorException('Item Out Of Stock');
      }else{
        return $min_checker;
      }

  }



    public function getDetailsFromVariation($variation_id, $business_id, $location_id = null, $check_qty = true)
    {
        $query = Variation::join('products AS p', 'variations.product_id', '=', 'p.id')
                ->join('product_variations AS pv', 'variations.product_variation_id', '=', 'pv.id')
                ->leftjoin('variation_location_details AS vld', 'variations.id', '=', 'vld.variation_id')
                ->leftjoin('units', 'p.unit_id', '=', 'units.id')
                ->leftjoin('brands', function ($join) {
                    $join->on('p.brand_id', '=', 'brands.id')
                        ->whereNull('brands.deleted_at');
                })
                ->where('p.business_id', $business_id)
                ->where('variations.id', $variation_id);



        $products = $query->select(
            DB::raw("IF(pv.is_dummy = 0, CONCAT(p.name, 
                    ' (', pv.name, ':',variations.name, ')'), p.name) AS product_name"),
            'p.id as product_id',
            'p.tax as tax_id',
            'p.enable_stock',
            'p.enable_sr_no',
            'p.name as product_actual_name',
            'pv.name as product_variation_name',
            'pv.is_dummy as is_dummy',
            'variations.name as variation_name',
            'variations.sub_sku',
            'p.barcode_type',
            'variations.default_sell_price',
            'variations.sell_price_inc_tax',
            'variations.id as variation_id',
            'units.short_name as unit',
            'units.allow_decimal as unit_allow_decimal',
            'brands.name as brand',
            DB::raw("(SELECT purchase_price_inc_tax FROM purchase_lines WHERE 
                        variation_id=variations.id ORDER BY id DESC LIMIT 1) as last_purchased_price")
        )
        ->first();

        return $products;
    }

    /**
     * Calculates the total amount of invoice
     *
     * @param array $products
     * @param int $tax_id
     * @param array $discount['discount_type', 'discount_amount']
     *
     * @return Mixed (false, array)
     */
    public function calculateInvoiceTotal($products, $tax_id, $discount = null)
    {

        if (empty($products)) {
            return false;
        }

        $output = ['total_before_tax' => 0, 'tax' => 0, 'discount' => 0, 'final_total' => 0];

        //Sub Total
        foreach ($products as $product) {
            $output['total_before_tax'] += $this->num_uf($product['unit_price_inc_tax']) * $this->num_uf($product['quantity']);

            //Add modifier price to total if exists
            if (!empty($product['modifier_price'])) {
                foreach ($product['modifier_price'] as $modifier_price) {
                    $output['total_before_tax'] += $this->num_uf($modifier_price);
                }
            }
        }

        //Calculate discount
        if (is_array($discount)) {
            if ($discount['discount_type'] == 'fixed') {
                $output['discount'] = $this->num_uf($discount['discount_amount']);
            } else {
                $output['discount'] = ($this->num_uf($discount['discount_amount'])/100)*$output['total_before_tax'];
            }
        }

        //Tax
        $output['tax'] = 0;
        if (!empty($tax_id)) {
            $tax_details = TaxRate::find($tax_id);
            if (!empty($tax_details)) {
                $output['tax_id'] = $tax_id;
                $output['tax'] = ($tax_details->amount/100) * ($output['total_before_tax'] - $output['discount']);
            }
        }
        
        //Calculate total
        $output['final_total'] = $output['total_before_tax'] + $output['tax'] - $output['discount'];
        
        return $output;
    }

    /**
     * Generates product sku
     *
     * @param string $string
     *
     * @return generated sku (string)
     */
    public function generateProductSku($string)
    {

        $business_id = request()->session()->get('user.business_id');
        $sku_prefix = Business::where('id', $business_id)->value('sku_prefix');

        return $sku_prefix . str_pad($string, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Gives list of trending products
     *
     * @param int $business_id
     * @param array $filters
     *
     * @return Obj
     */
    public function getTrendingProducts($business_id, $filters = [])
    {
        $query = Transaction::join(
            'transaction_sell_lines as tsl',
            'transactions.id',
            '=',
            'tsl.transaction_id'
        )
                    ->join('products as p', 'tsl.product_id', '=', 'p.id')
                    ->leftjoin('units as u', 'u.id', '=', 'p.unit_id')
                    ->where('transactions.business_id', $business_id)
                    ->where('transactions.type', 'sell')
                    ->where('transactions.status', 'final');

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $query->whereIn('transactions.location_id', $permitted_locations);
        }
        if (!empty($filters['location_id'])) {
            $query->where('transactions.location_id', $filters['location_id']);
        }
        if (!empty($filters['category'])) {
            $query->where('p.category_id', $filters['category']);
        }
        if (!empty($filters['sub_category'])) {
            $query->where('p.sub_category_id', $filters['sub_category']);
        }
        if (!empty($filters['brand'])) {
            $query->where('p.brand_id', $filters['brand']);
        }
        if (!empty($filters['unit'])) {
            $query->where('p.unit_id', $filters['unit']);
        }
        if (!empty($filters['limit'])) {
            $query->limit($filters['limit']);
        } else {
            $query->limit(5);
        }
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween(DB::raw('date(transaction_date)'), [$filters['start_date'],
                $filters['end_date']]);
        }

        $sell_return_query = "(SELECT SUM(TPL.quantity) FROM transactions AS T JOIN purchase_lines AS TPL ON T.id=TPL.transaction_id WHERE TPL.product_id=tsl.product_id AND T.type='sell_return'";
        if ($permitted_locations != 'all') {
            $sell_return_query .= ' AND T.location_id IN ('
             . implode(',', $permitted_locations) . ') ';
        }
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $sell_return_query .= ' AND date(T.transaction_date) BETWEEN \'' . $filters['start_date'] . '\' AND \'' . $filters['end_date'] . '\'';
        }
        $sell_return_query .= ')';

        $products = $query->select(
            DB::raw("(SUM(tsl.quantity) - COALESCE($sell_return_query, 0)) as total_unit_sold"),
            'p.name as product',
            'u.short_name as unit'
        )
                        ->groupBy('tsl.product_id')
                        ->orderBy('total_unit_sold', 'desc')
                        ->get();
        return $products;
    }

    /**
     * Gives list of products based on products id and variation id
     *
     * @param int $business_id
     * @param int $product_id
     * @param int $variation_id = null
     *
     * @return Obj
     */
    public function getDetailsFromProduct($business_id, $product_id, $variation_id = null)
    {
        $product = Product::leftjoin('variations as v', 'products.id', '=', 'v.product_id')
                        ->where('products.business_id', $business_id);

        if (!is_null($variation_id) && $variation_id !== '0') {
            $product->where('v.id', $variation_id);
        }

        $product->where('products.id', $product_id);

        $products = $product->select(
            'products.id as product_id',
            'products.name as product_name',
            'v.id as variation_id',
            'v.name as variation_name'
        )
                    ->get();

        return $products;
    }

    /**
     * F => D (Previous product Increase)
     * D => F (All product decrease)
     * F => F (Newly added product drerease)
     *
     * @param  object $transaction_before
     * @param  object  $transaction
     * @param  array  $input
     *
     * @return void
     */
    public function adjustProductStockForInvoice($status_before,$service_center, $transaction, $input)
    {
       

        if ($status_before == 'final' && $transaction->status == 'draft') {
            foreach ($input['products'] as $product) {
                if (!empty($product['transaction_sell_lines_id'])) {
                    $this->updateProductQuantity($input['location_id'], $product['product_id'], $product['variation_id'], $product['quantity']);
                }
            }
        } elseif ($status_before == 'draft' && $transaction->status == 'final') {
            foreach ($input['products'] as $product) {
                $this->decreaseProductQuantity(
                    $product['product_id'],
                    $product['variation_id'],
                    $input['location_id'],
                    $service_center,
                    $this->num_uf($product['quantity'])
                );
            }
        } elseif ($status_before == 'final' && $transaction->status == 'final') {
        
            foreach ($input['products'] as $product) {
                if (empty($product['transaction_sell_lines_id'])) {
                    $this->decreaseProductQuantity(
                        $product['product_id'],
                        $product['variation_id'],
                        $input['location_id'],
                        $service_center,
                        $this->num_uf($product['quantity'])
                    );
                }
            }
        }
    }

    /**
     * Updates variation from purchase screen
     *
     * @param array $variation_data
     *
     * @return void
     */
    public function updateProductFromPurchase($variation_data)
    {
        $variation_details = Variation::where('id', $variation_data['variation_id'])
                                        ->with(['product', 'product.product_tax'])
                                        ->first();
        $tax_rate = 0;
        if (!empty($variation_details->product->product_tax->amount)) {
            $tax_rate = $variation_details->product->product_tax->amount;
        }

        if (($variation_details->default_purchase_price != $variation_data['pp_without_discount']) ||
            ($variation_details->default_sell_price != $variation_data['default_sell_price'])
            ) {
            //Set default purchase price exc. tax
            $variation_details->default_purchase_price = $variation_data['pp_without_discount'];

            //Set default purchase price inc. tax
            $variation_details->dpp_inc_tax = $this->calc_percentage($variation_details->default_purchase_price, $tax_rate, $variation_details->default_purchase_price);
       
            //Set default sell price exc. tax
            $variation_details->default_sell_price = $variation_data['default_sell_price'];

            //set profit margin
            $variation_details->profit_percent = $this->get_percent($variation_details->default_purchase_price, $variation_details->default_sell_price);

            //set sell price inc. tax
            $variation_details->sell_price_inc_tax = $this->calc_percentage($variation_details->default_sell_price, $tax_rate, $variation_details->default_sell_price);
            
            $variation_details->save();
        }
    }

    /**
     * Generated SKU based on the barcode type.
     *
     * @param string $sku
     * @param string $c
     * @param string $barcode_type
     *
     * @return void
     */
    public function generateSubSku($sku, $c, $barcode_type)
    {
        $sub_sku = $sku . $c;

        if (in_array($barcode_type, ['C128', 'C39'])) {
            $sub_sku = $sku . '-' . $c;
        }

        return $sub_sku;
    }

    /**
     * Add rack details.
     *
     * @param int $business_id
     * @param int $product_id
     * @param array $product_racks
     * @param array $product_racks
     *
     * @return void
     */
    public function addRackDetails($business_id, $product_id, $product_racks)
    {

        if (!empty($product_racks)) {
            $data = [];
            foreach ($product_racks as $location_id => $detail) {
                $data[] = ['business_id' => $business_id,
                        'location_id' => $location_id,
                        'product_id' => $product_id,
                        'rack' => !empty($detail['rack']) ? $detail['rack'] : null,
                        'row' => !empty($detail['row']) ? $detail['row'] : null,
                        'position' => !empty($detail['position']) ? $detail['position'] : null,
                        'created_at' => \Carbon::now()->toDateTimeString(),
                        'updated_at' => \Carbon::now()->toDateTimeString()
                    ];
            }

            ProductRack::insert($data);
        }
    }

    /**
     * Get rack details.
     *
     * @param int $business_id
     * @param int $product_id
     *
     * @return void
     */
    public function getRackDetails($business_id, $product_id, $get_location = false)
    {

        $query = ProductRack::where('product_racks.business_id', $business_id)
                    ->where('product_id', $product_id);

        if ($get_location) {
            $racks = $query->join('business_locations AS BL', 'product_racks.location_id', '=', 'BL.id')
                ->select(['product_racks.rack',
                        'product_racks.row',
                        'product_racks.position',
                        'BL.name'])
                ->get();
        } else {
            $racks = collect($query->select(['rack', 'row', 'position', 'location_id'])->get());

            $racks = $racks->mapWithKeys(function ($item, $key) {
                return [$item['location_id'] => $item->toArray()];
            })->toArray();
        }

        return $racks;
    }

    /**
     * Update rack details.
     *
     * @param int $business_id
     * @param int $product_id
     * @param array $product_racks
     *
     * @return void
     */
    public function updateRackDetails($business_id, $product_id, $product_racks)
    {

        if (!empty($product_racks)) {
            foreach ($product_racks as $location_id => $details) {
                ProductRack::where('business_id', $business_id)
                    ->where('product_id', $product_id)
                    ->where('location_id', $location_id)
                    ->update(['rack' => !empty($details['rack']) ? $details['rack'] : null,
                            'row' => !empty($details['row']) ? $details['row'] : null,
                            'position' => !empty($details['position']) ? $details['position'] : null
                        ]);
            }
        }
    }

    /**
     * Retrieves selling price group price for a product variation.
     *
     * @param int $variation_id
     * @param int $price_group_id
     * @param int $tax_id
     *
     * @return decimal
     */
    public function getVariationGroupPrice($variation_id, $price_group_id, $tax_id)
    {
        $price_inc_tax =
        VariationGroupPrice::where('variation_id', $variation_id)
                        ->where('price_group_id', $price_group_id)
                        ->value('price_inc_tax');

        $price_exc_tax = $price_inc_tax;
        if (!empty($price_inc_tax) && !empty($tax_id)) {
            $tax_amount = TaxRate::where('id', $tax_id)->value('amount');
            $price_exc_tax = $this->calc_percentage_base($price_inc_tax, $tax_amount);
        }
        return [
            'price_inc_tax' => $price_inc_tax,
            'price_exc_tax' => $price_exc_tax
        ];
    }

    /**
     * Creates new variation if not exists.
     *
     * @param int $business_id
     * @param string $name
     *
     * @return obj
     */
    public function createOrNewVariation($business_id, $name)
    {
        $variation = VariationTemplate::where('business_id', $business_id)
                                    ->where('name', 'like', $name)
                                    ->with(['values'])
                                    ->first();

        if (empty($variation)) {
            $variation = VariationTemplate::create([
            'business_id' => $business_id,
            'name' => $name
            ]);
        }
        return $variation;
    }

    /**
     * Adds opening stock to a single product.
     *
     * @param int $business_id
     * @param obj $product
     * @param array $input
     * @param obj $transaction_date
     * @param int $user_id
     *
     * @return void
     */
    public function addSingleProductOpeningStock($business_id, $product, $input, $transaction_date, $user_id)
    {
      $locations = BusinessLocation::forDropdown($business_id)->toArray();

      $tax_percent = !empty($product->product_tax->amount) ? $product->product_tax->amount : 0;
      $tax_id = !empty($product->product_tax->id) ? $product->product_tax->id : null;

      foreach ($input as $key => $value) {
        $location_id = $key;
        $purchase_total = 0;
        //Check if valid location
        if (array_key_exists($location_id, $locations)) {
            $purchase_lines = [];

            $purchase_price = $this->num_uf(trim($value['purchase_price']));
            $item_tax = $this->calc_percentage($purchase_price, $tax_percent);
            $purchase_price_inc_tax = $purchase_price + $item_tax;
            $qty = $this->num_uf(trim($value['quantity']));

            $exp_date = null;
            if (!empty($value['exp_date'])) {
                $exp_date = \Carbon::createFromFormat('d-m-Y', $value['exp_date'])->format('Y-m-d');
            }

            $lot_number = null;
            if (!empty($value['lot_number'])) {
                $lot_number = $value['lot_number'];
            }

            if ($qty > 0) {
              $qty_formated = $this->num_f($qty);
              //Calculate transaction total
              $purchase_total += ($purchase_price_inc_tax * $qty);
              $variation_id = $product->variations->first()->id;

              $purchase_line = new PurchaseLine();
              $purchase_line->product_id = $product->id;
              $purchase_line->variation_id = $variation_id;
              $purchase_line->item_tax = $item_tax;
              $purchase_line->tax_id = $tax_id;
              $purchase_line->quantity = $qty;
              $purchase_line->pp_without_discount = $purchase_price;
              $purchase_line->purchase_price = $purchase_price;
              $purchase_line->purchase_price_inc_tax = $purchase_price_inc_tax;
              $purchase_line->exp_date = $exp_date;
              $purchase_line->lot_number = $lot_number;
              $purchase_lines[] = $purchase_line;

              $this->updateProductQuantity($location_id, $product->id, $variation_id, $qty_formated);
            }

            //create transaction & purchase lines
            if (!empty($purchase_lines)) {
              $transaction = Transaction::create(
                [
                  'type' => 'opening_stock',
                  'opening_stock_product_id' => $product->id,
                  'status' => 'received',
                  'business_id' => $business_id,
                  'transaction_date' => $transaction_date,
                  'total_before_tax' => $purchase_total,
                  'location_id' => $location_id,
                  'final_total' => $purchase_total,
                  'payment_status' => 'paid',
                  'created_by' => $user_id
                ]
              );
              $transaction->purchase_lines()->saveMany($purchase_lines);
            }

        }
      }
    }
}
