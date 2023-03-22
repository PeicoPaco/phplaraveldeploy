<?php
namespace App\CentralLogics;

use App\Models\Zone;
use App\Models\AddOn;
use App\Models\Order;
use App\Models\Review;
use App\Models\Expense;
use App\Models\TimeLog;
use App\Models\Currency;
use App\Models\DMReview;
use App\Mail\OrderPlaced;
use App\Models\Restaurant;
use Illuminate\Support\Str;
use App\Mail\RefundRejected;
use Illuminate\Support\Carbon;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use App\Models\SubscriptionPackage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\CentralLogics\RestaurantLogic;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use Laravelpkg\Laravelchk\Http\Controllers\LaravelchkController;
use Illuminate\Support\Facades\Http;

class Helpers
{
    public static function error_processor($validator)
    {
        $err_keeper = [];
        foreach ($validator->errors()->getMessages() as $index => $error) {
            array_push($err_keeper, ['code' => $index, 'message' => $error[0]]);
        }
        return $err_keeper;
    }

    public static function error_formater($key, $mesage, $errors = [])
    {
        $errors[] = ['code' => $key, 'message' => $mesage];

        return $errors;
    }

    public static function schedule_order()
    {
        return (bool)BusinessSetting::where(['key' => 'schedule_order'])->first()->value;
    }


    public static function combinations($arrays)
    {
        $result = [[]];
        foreach ($arrays as $property => $property_values) {
            $tmp = [];
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, [$property => $property_value]);
                }
            }
            $result = $tmp;
        }
        return $result;
    }

    public static function variation_price($product, $variations)
    {
        // $match = json_decode($variations, true)[0];
        $match = $variations;
        $result = 0;
        // foreach (json_decode($product['variations'], true) as $property => $value) {
        //     if ($value['type'] == $match['type']) {
        //         $result = $value['price'];
        //     }
        // }
            foreach($product as $product_variation){
                foreach($product_variation['values'] as $option){
                    foreach($match as $variation){
                        if($product_variation['name'] == $variation['name'] && isset($variation['values']) && in_array($option['label'], $variation['values']['label'])){
                            $result += $option['optionPrice'];
                        }
                    }
                }
            }

        return $result;
    }

    public static function product_data_formatting($data, $multi_data = false, $trans = false, $local = 'en')
    {
        $storage = [];
        if ($multi_data == true) {
            foreach ($data as $item) {
                $variations = [];
                if ($item->title) {
                    $item['name'] = $item->title;
                    unset($item['title']);
                }
                if ($item->start_time) {
                    $item['available_time_starts'] = $item->start_time->format('H:i');
                    unset($item['start_time']);
                }
                if ($item->end_time) {
                    $item['available_time_ends'] = $item->end_time->format('H:i');
                    unset($item['end_time']);
                }

                if ($item->start_date) {
                    $item['available_date_starts'] = $item->start_date->format('Y-m-d');
                    unset($item['start_date']);
                }
                if ($item->end_date) {
                    $item['available_date_ends'] = $item->end_date->format('Y-m-d');
                    unset($item['end_date']);
                }
                $item['recommended'] =(int) $item->recommended;
                $categories = [];
                foreach (json_decode($item['category_ids']) as $value) {
                    $categories[] = ['id' => (string)$value->id, 'position' => $value->position];
                }
                $item['category_ids'] = $categories;
                $item['attributes'] = json_decode($item['attributes']);
                $item['choice_options'] = json_decode($item['choice_options']);
                $item['add_ons'] = self::addon_data_formatting(AddOn::withoutGlobalScope('translate')->whereIn('id', json_decode($item['add_ons']))->active()->get(), true, $trans, $local);
                // foreach (json_decode($item['variations'], true) as $var) {
                //     array_push($variations, [
                //         'type' => $var['type'],
                //         'price' => (float)$var['price']
                //     ]);
                // }

                $item['tags'] = $item->tags;
                $item['variations'] = json_decode($item['variations'], true);
                $item['restaurant_name'] = $item->restaurant->name;
                $item['restaurant_status'] = (int) $item->restaurant->status;
                $item['restaurant_discount'] = self::get_restaurant_discount($item->restaurant) ? $item->restaurant->discount->discount : 0;
                $item['restaurant_opening_time'] = $item->restaurant->opening_time ? $item->restaurant->opening_time->format('H:i') : null;
                $item['restaurant_closing_time'] = $item->restaurant->closeing_time ? $item->restaurant->closeing_time->format('H:i') : null;
                $item['schedule_order'] = $item->restaurant->schedule_order;
                $item['tax'] = $item->restaurant->tax;
                $item['rating_count'] = (int)($item->rating ? array_sum(json_decode($item->rating, true)) : 0);
                $item['avg_rating'] = (float)($item->avg_rating ? $item->avg_rating : 0);

                if ($trans) {
                    $item['translations'][] = [
                        'translationable_type' => 'App\Models\Food',
                        'translationable_id' => $item->id,
                        'locale' => 'en',
                        'key' => 'name',
                        'value' => $item->name
                    ];

                    $item['translations'][] = [
                        'translationable_type' => 'App\Models\Food',
                        'translationable_id' => $item->id,
                        'locale' => 'en',
                        'key' => 'description',
                        'value' => $item->description
                    ];
                }

                if (count($item['translations']) > 0) {
                    foreach ($item['translations'] as $translation) {
                        if ($translation['locale'] == $local) {
                            if ($translation['key'] == 'name') {
                                $item['name'] = $translation['value'];
                            }

                            if ($translation['key'] == 'title') {
                                $item['name'] = $translation['value'];
                            }

                            if ($translation['key'] == 'description') {
                                $item['description'] = $translation['value'];
                            }
                        }
                    }
                }
                if (!$trans) {
                    unset($item['translations']);
                }

                unset($item['restaurant']);
                unset($item['rating']);
                array_push($storage, $item);
            }
            $data = $storage;
        } else {
            $variations = [];
            $categories = [];
            foreach (json_decode($data['category_ids']) as $value) {
                $categories[] = ['id' => (string)$value->id, 'position' => $value->position];
            }
            $data['category_ids'] = $categories;
            // $data['category_ids'] = json_decode($data['category_ids']);
            $data['attributes'] = json_decode($data['attributes']);
            $data['choice_options'] = json_decode($data['choice_options']);
            $data['add_ons'] = self::addon_data_formatting(AddOn::whereIn('id', json_decode($data['add_ons']))->active()->get(), true, $trans, $local);
            // foreach (json_decode($data['variations'], true) as $var) {
            //     array_push($variations, [
            //         'type' => $var['type'],
            //         'price' => (float)$var['price']
            //     ]);
            // }
            if ($data->title) {
                $data['name'] = $data->title;
                unset($data['title']);
            }
            if ($data->start_time) {
                $data['available_time_starts'] = $data->start_time->format('H:i');
                unset($data['start_time']);
            }
            if ($data->end_time) {
                $data['available_time_ends'] = $data->end_time->format('H:i');
                unset($data['end_time']);
            }
            if ($data->start_date) {
                $data['available_date_starts'] = $data->start_date->format('Y-m-d');
                unset($data['start_date']);
            }
            if ($data->end_date) {
                $data['available_date_ends'] = $data->end_date->format('Y-m-d');
                unset($data['end_date']);
            }
            $data['variations'] = json_decode($data['variations'], true);
            $data['restaurant_name'] = $data->restaurant->name;
            $data['restaurant_status'] = (int) $data->restaurant->status;
            $data['restaurant_discount'] = self::get_restaurant_discount($data->restaurant) ? $data->restaurant->discount->discount : 0;
            $data['restaurant_opening_time'] = $data->restaurant->opening_time ? $data->restaurant->opening_time->format('H:i') : null;
            $data['restaurant_closing_time'] = $data->restaurant->closeing_time ? $data->restaurant->closeing_time->format('H:i') : null;
            $data['schedule_order'] = $data->restaurant->schedule_order;
            $data['rating_count'] = (int)($data->rating ? array_sum(json_decode($data->rating, true)) : 0);
            $data['avg_rating'] = (float)($data->avg_rating ? $data->avg_rating : 0);
            $data['recommended'] =(int) $data->recommended;
            if ($trans) {
                $data['translations'][] = [
                    'translationable_type' => 'App\Models\Food',
                    'translationable_id' => $data->id,
                    'locale' => 'en',
                    'key' => 'name',
                    'value' => $data->name
                ];

                $data['translations'][] = [
                    'translationable_type' => 'App\Models\Food',
                    'translationable_id' => $data->id,
                    'locale' => 'en',
                    'key' => 'description',
                    'value' => $data->description
                ];
            }

            if (count($data['translations']) > 0) {
                foreach ($data['translations'] as $translation) {
                    if ($translation['locale'] == $local) {
                        if ($translation['key'] == 'name') {
                            $data['name'] = $translation['value'];
                        }

                        if ($translation['key'] == 'title') {
                            $item['name'] = $translation['value'];
                        }

                        if ($translation['key'] == 'description') {
                            $data['description'] = $translation['value'];
                        }
                    }
                }
            }
            if (!$trans) {
                unset($data['translations']);
            }

            unset($data['restaurant']);
            unset($data['rating']);
        }

        return $data;
    }

    public static function addon_data_formatting($data, $multi_data = false, $trans = false, $local = 'en')
    {
        $storage = [];
        if ($multi_data == true) {
            foreach ($data as $item) {
                if ($trans) {
                    $item['translations'][] = [
                        'translationable_type' => 'App\Models\AddOn',
                        'translationable_id' => $item->id,
                        'locale' => 'en',
                        'key' => 'name',
                        'value' => $item->name
                    ];
                }
                if (count($item->translations) > 0) {
                    foreach ($item['translations'] as $translation) {
                        if ($translation['locale'] == $local && $translation['key'] == 'name') {
                            $item['name'] = $translation['value'];
                        }
                    }
                }

                if (!$trans) {
                    unset($item['translations']);
                }

                $storage[] = $item;
            }
            $data = $storage;
        } else if (isset($data)) {
            if ($trans) {
                $data['translations'][] = [
                    'translationable_type' => 'App\Models\AddOn',
                    'translationable_id' => $data->id,
                    'locale' => 'en',
                    'key' => 'name',
                    'value' => $data->name
                ];
            }

            if (count($data->translations) > 0) {
                foreach ($data['translations'] as $translation) {
                    if ($translation['locale'] == $local && $translation['key'] == 'name') {
                        $data['name'] = $translation['value'];
                    }
                }
            }

            if (!$trans) {
                unset($data['translations']);
            }
        }
        return $data;
    }

    public static function category_data_formatting($data, $multi_data = false, $trans = false)
    {
        $storage = [];
        if ($multi_data == true) {
            foreach ($data as $item) {
                if (count($item->translations) > 0) {
                    $item->name = $item->translations[0]['value'];
                }

                if (!$trans) {
                    unset($item['translations']);
                }

                if($item->relationLoaded('childes') && $item['childes']){
                    $item['products_count'] += $item['childes']->sum('products_count');
                    unset($item['childes']);
                }
                $storage[] = $item;
            }
            $data = $storage;
        } else if (isset($data)) {
            if (count($data->translations) > 0) {
                $data->name = $data->translations[0]['value'];
            }

            if (!$trans) {
                unset($data['translations']);
            }
            if($data->relationLoaded('childes') && $data['childes']){
                $data['products_count'] += $data['childes']->sum('products_count');
                unset($data['childes']);
            }
        }
        return $data;
    }

    public static function basic_campaign_data_formatting($data, $multi_data = false)
    {
        $storage = [];
        if ($multi_data == true) {
            foreach ($data as $item) {
                $variations = [];

                if ($item->start_date) {
                    $item['available_date_starts'] = $item->start_date->format('Y-m-d');
                    unset($item['start_date']);
                }
                if ($item->end_date) {
                    $item['available_date_ends'] = $item->end_date->format('Y-m-d');
                    unset($item['end_date']);
                }

                if (count($item['translations']) > 0) {
                    $translate = array_column($item['translations']->toArray(), 'value', 'key');
                    $item['title'] = $translate['title'];
                    $item['description'] = $translate['description'];
                }
                if (count($item['restaurants']) > 0) {
                    $item['restaurants'] = self::restaurant_data_formatting($item['restaurants'], true);
                }

                array_push($storage, $item);
            }
            $data = $storage;
        } else {
            if ($data->start_date) {
                $data['available_date_starts'] = $data->start_date->format('Y-m-d');
                unset($data['start_date']);
            }
            if ($data->end_date) {
                $data['available_date_ends'] = $data->end_date->format('Y-m-d');
                unset($data['end_date']);
            }

            if (count($data['translations']) > 0) {
                $translate = array_column($data['translations']->toArray(), 'value', 'key');
                $data['title'] = $translate['title'];
                $data['description'] = $translate['description'];
            }
            if (count($data['restaurants']) > 0) {
                $data['restaurants'] = self::restaurant_data_formatting($data['restaurants'], true);
            }
        }

        return $data;
    }
    public static function restaurant_data_formatting($data, $multi_data = false)
    {
        $storage = [];
        $cuisines=[];

        if ($multi_data == true) {
            foreach ($data as $item) {
                if( $item->restaurant_model == 'subscription'  && isset($item->restaurant_sub)){
                    $item['self_delivery_system'] = (int) $item->restaurant_sub->self_delivery;
                }
                $item['restaurant_status'] = (int) $item->status;
                if ($item->cuisine) {

                        foreach($item->cuisine as $cuisine){
                            $cuisines[]=$cuisine;
                        }

                }


                if ($item->opening_time) {
                    $item['available_time_starts'] = $item->opening_time->format('H:i');
                    unset($item['opening_time']);
                }
                if ($item->closeing_time) {
                    $item['available_time_ends'] = $item->closeing_time->format('H:i');
                    unset($item['closeing_time']);
                }

                $ratings = RestaurantLogic::calculate_restaurant_rating($item['rating']);
                unset($item['rating']);
                $item['avg_rating'] = $ratings['rating'];
                $item['rating_count '] = $ratings['total'];
                unset($item['campaigns']);
                unset($item['pivot']);
                array_push($storage, $item);
            }
            $data = $storage;
        } else {
            if( $data->restaurant_model == 'subscription'  && isset($data->restaurant_sub)){
                $data['self_delivery_system'] = (int) $data->restaurant_sub->self_delivery;
            }
            $data['restaurant_status'] = (int) $data->status;
            if ($data->opening_time) {
                $data['available_time_starts'] = $data->opening_time->format('H:i');
                unset($data['opening_time']);
            }
            if ($data->closeing_time) {
                $data['available_time_ends'] = $data->closeing_time->format('H:i');
                unset($data['closeing_time']);
            }

        if ($data->cuisine) {
            foreach($data->cuisine as $cuisine){
                $cuisines[]=$cuisine;
            }
    }

            $ratings = RestaurantLogic::calculate_restaurant_rating($data['rating']);
            unset($data['rating']);
            $data['avg_rating'] = $ratings['rating'];
            $data['rating_count '] = $ratings['total'];
            unset($data['campaigns']);
            unset($data['pivot']);
        }

        return $data;
    }

    public static function wishlist_data_formatting($data, $multi_data = false)
    {
        $foods = [];
        $restaurants = [];
        if ($multi_data == true) {

            foreach ($data as $item) {
                if ($item->food) {
                    $foods[] = self::product_data_formatting($item->food, false, false, app()->getLocale());
                }
                if ($item->restaurant) {
                    $restaurants[] = self::restaurant_data_formatting($item->restaurant);
                }
            }
        } else {
            if ($data->food) {
                $foods[] = self::product_data_formatting($data->food, false, false, app()->getLocale());
            }
            if ($data->restaurant) {
                $restaurants[] = self::restaurant_data_formatting($data->restaurant);
            }
        }

        return ['food' => $foods, 'restaurant' => $restaurants];
    }

    public static function order_data_formatting($data, $multi_data = false)
    {
        $storage = [];
        if ($multi_data) {
            foreach ($data as $item) {
                if (isset($item['restaurant'])) {
                    $item['restaurant_name'] = $item['restaurant']['name'];
                    $item['restaurant_address'] = $item['restaurant']['address'];
                    $item['restaurant_phone'] = $item['restaurant']['phone'];
                    $item['restaurant_lat'] = $item['restaurant']['latitude'];
                    $item['restaurant_lng'] = $item['restaurant']['longitude'];
                    $item['restaurant_logo'] = $item['restaurant']['logo'];
                    $item['restaurant_delivery_time'] = $item['restaurant']['delivery_time'];
                    $item['vendor_id'] = $item['restaurant']['vendor_id'];
                    $item['chat_permission'] = $item['restaurant']['restaurant_sub']['chat'] ?? 0;
                    $item['restaurant_model'] = $item['restaurant']['restaurant_model'];
                    unset($item['restaurant']);
                } else {
                    $item['restaurant_name'] = null;
                    $item['restaurant_address'] = null;
                    $item['restaurant_phone'] = null;
                    $item['restaurant_lat'] = null;
                    $item['restaurant_lng'] = null;
                    $item['restaurant_logo'] = null;
                    $item['restaurant_delivery_time'] = null;
                    $item['restaurant_model'] = null;
                    $item['chat_permission'] = null;
                }
                $item['food_campaign'] = 0;
                foreach ($item->details as $d) {
                    if ($d->item_campaign_id != null) {
                        $item['food_campaign'] = 1;
                    }
                }

                $item['delivery_address'] = $item->delivery_address ? json_decode($item->delivery_address, true) : null;
                $item['details_count'] = (int)$item->details->count();
                unset($item['details']);
                array_push($storage, $item);
            }
            $data = $storage;
        } else {
            if (isset($data['restaurant'])) {
                $data['restaurant_name'] = $data['restaurant']['name'];
                $data['restaurant_address'] = $data['restaurant']['address'];
                $data['restaurant_phone'] = $data['restaurant']['phone'];
                $data['restaurant_lat'] = $data['restaurant']['latitude'];
                $data['restaurant_lng'] = $data['restaurant']['longitude'];
                $data['restaurant_logo'] = $data['restaurant']['logo'];
                $data['restaurant_delivery_time'] = $data['restaurant']['delivery_time'];
                $data['vendor_id'] = $data['restaurant']['vendor_id'];
                $data['chat_permission'] = $data['restaurant']['restaurant_sub']['chat'] ?? 0;
                $data['restaurant_model'] = $data['restaurant']['restaurant_model'];
                unset($data['restaurant']);
            } else {
                $data['restaurant_name'] = null;
                $data['restaurant_address'] = null;
                $data['restaurant_phone'] = null;
                $data['restaurant_lat'] = null;
                $data['restaurant_lng'] = null;
                $data['restaurant_logo'] = null;
                $data['restaurant_delivery_time'] = null;
                $data['chat_permission'] = null;
                $data['restaurant_model'] = null;
            }

            $data['food_campaign'] = 0;
            foreach ($data->details as $d) {
                if ($d->item_campaign_id != null) {
                    $data['food_campaign'] = 1;
                }
            }
            $data['delivery_address'] = $data->delivery_address ? json_decode($data->delivery_address, true) : null;
            $data['details_count'] = (int)$data->details->count();
            unset($data['details']);
        }
        return $data;
    }

    public static function order_details_data_formatting($data)
    {
        $storage = [];
        foreach ($data as $item) {
            $item['add_ons'] = json_decode($item['add_ons']);
            $item['variation'] = json_decode($item['variation']);
            $item['food_details'] = json_decode($item['food_details'], true);
            array_push($storage, $item);
        }
        $data = $storage;

        return $data;
    }

    public static function deliverymen_list_formatting($data)
    {
        $storage = [];
        foreach ($data as $item) {
            $storage[] = [
                'id' => $item['id'],
                'name' => $item['f_name'] . ' ' . $item['l_name'],
                'image' => $item['image'],
                'lat' => $item->last_location ? $item->last_location->latitude : false,
                'lng' => $item->last_location ? $item->last_location->longitude : false,
                'location' => $item->last_location ? $item->last_location->location : '',
            ];
        }
        $data = $storage;

        return $data;
    }

    public static function address_data_formatting($data)
    {
        foreach ($data as $key=>$item) {
            $point = new Point($item->latitude, $item->longitude);
            $data[$key]['zone_ids'] = array_column(Zone::contains('coordinates', $point)->latest()->get(['id'])->toArray(), 'id');;
        }
        return $data;
    }

    public static function deliverymen_data_formatting($data)
    {
        $storage = [];
        foreach ($data as $item) {
            $item['avg_rating'] = (float)(count($item->rating) ? (float)$item->rating[0]->average : 0);
            $item['rating_count'] = (int)(count($item->rating) ? $item->rating[0]->rating_count : 0);
            $item['lat'] = $item->last_location ? $item->last_location->latitude : null;
            $item['lng'] = $item->last_location ? $item->last_location->longitude : null;
            $item['location'] = $item->last_location ? $item->last_location->location : null;
            if ($item['rating']) {
                unset($item['rating']);
            }
            if ($item['last_location']) {
                unset($item['last_location']);
            }
            $storage[] = $item;
        }
        $data = $storage;

        return $data;
    }

    public static function get_business_settings($name, $json_decode = true)
    {
        $config = null;

        $paymentmethod = BusinessSetting::where('key', $name)->first();

        if ($paymentmethod) {
            $config = $json_decode ? json_decode($paymentmethod->value, true) : $paymentmethod->value;
        }

        return $config;
    }

    public static function currency_code()
    {
        return BusinessSetting::where(['key' => 'currency'])->first()->value;
    }

    public static function currency_symbol()
    {
        $currency_symbol = Currency::where(['currency_code' => Helpers::currency_code()])->first()->currency_symbol;
        return $currency_symbol;
    }

    public static function format_currency($value)
    {
        $currency_symbol_position = BusinessSetting::where(['key' => 'currency_symbol_position'])->first()->value;

        return $currency_symbol_position == 'right' ? number_format($value, config('round_up_to_digit')) . ' ' . self::currency_symbol() : self::currency_symbol() . ' ' . number_format($value, config('round_up_to_digit'));
    }
    public static function send_push_notif_to_device($fcm_token, $data, $web_push_link = null)
    {
        $key = BusinessSetting::where(['key' => 'push_notification_key'])->first()->value;
        $url = "https://fcm.googleapis.com/fcm/send";
        $header = array(
            "authorization: key=" . $key . "",
            "content-type: application/json"
        );

        if(isset($data['conversation_id'])){
            $conversation_id = $data['conversation_id'];
        }else{
            $conversation_id = '';
        }
        if(isset($data['sender_type'])){
            $sender_type = $data['sender_type'];
        }else{
            $sender_type = '';
        }
        if(isset($data['order_type'])){
            $order_type = $data['order_type'];
        }else{
            $order_type = '';
        }

        $click_action = "";
        if($web_push_link){
            $click_action = ',
            "click_action": "'.$web_push_link.'"';
        }

        $postdata = '{
            "to" : "' . $fcm_token . '",
            "mutable_content": true,
            "data" : {
                "title":"' . $data['title'] . '",
                "body" : "' . $data['description'] . '",
                "image" : "' . $data['image'] . '",
                "order_id":"' . $data['order_id'] . '",
                "type":"' . $data['type'] . '",
                "conversation_id":"' . $conversation_id . '",
                "sender_type":"' . $sender_type . '",
                "order_type":"' . $order_type . '",
                "is_read": 0
            },
            "notification" : {
                "title" :"' . $data['title'] . '",
                "body" : "' . $data['description'] . '",
                "image" : "' . $data['image'] . '",
                "order_id":"' . $data['order_id'] . '",
                "title_loc_key":"' . $data['order_id'] . '",
                "body_loc_key":"' . $data['type'] . '",
                "type":"' . $data['type'] . '",
                "is_read": 0,
                "icon" : "new",
                "sound": "notification.wav",
                "android_channel_id": "stackfood"
                '.$click_action.'
            }
        }';

        $ch = curl_init();
        $timeout = 120;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        // Get URL content
        $result = curl_exec($ch);
        // close handle to release resources
        curl_close($ch);

        return $result;
    }

    public static function send_push_notif_to_topic($data, $topic, $type, $web_push_link = null)
    {
        info([$data, $topic, $type, $web_push_link]);
        $key = BusinessSetting::where(['key' => 'push_notification_key'])->first()->value;

        $url = "https://fcm.googleapis.com/fcm/send";
        $header = array(
            "authorization: key=" . $key . "",
            "content-type: application/json"
        );

        if(isset($data['order_type'])){
            $order_type = $data['order_type'];
        }else{
            $order_type = '';
        }
        $click_action = "";
        if($web_push_link){
            $click_action = ',
            "click_action": "'.$web_push_link.'"';
        }

        if (isset($data['order_id'])) {
            $postdata = '{
                "to" : "/topics/' . $topic . '",
                "mutable_content": true,
                "data" : {
                    "title":"' . $data['title'] . '",
                    "body" : "' . $data['description'] . '",
                    "image" : "' . $data['image'] . '",
                    "order_id":"' . $data['order_id'] . '",
                    "order_type":"' . $order_type . '",
                    "is_read": 0,
                    "type":"' . $type . '"
                },
                "notification" : {
                    "title":"' . $data['title'] . '",
                    "body" : "' . $data['description'] . '",
                    "image" : "' . $data['image'] . '",
                    "order_id":"' . $data['order_id'] . '",
                    "title_loc_key":"' . $data['order_id'] . '",
                    "body_loc_key":"' . $type . '",
                    "type":"' . $type . '",
                    "is_read": 0,
                    "icon" : "new",
                    "sound": "notification.wav",
                    "android_channel_id": "stackfood"
                    '.$click_action.'
                  }
            }';
        } else {
            $postdata = '{
                "to" : "/topics/' . $topic . '",
                "mutable_content": true,
                "data" : {
                    "title":"' . $data['title'] . '",
                    "body" : "' . $data['description'] . '",
                    "image" : "' . $data['image'] . '",
                    "is_read": 0,
                    "type":"' . $type . '",
                },
                "notification" : {
                    "title":"' . $data['title'] . '",
                    "body" : "' . $data['description'] . '",
                    "image" : "' . $data['image'] . '",
                    "body_loc_key":"' . $type . '",
                    "type":"' . $type . '",
                    "is_read": 0,
                    "icon" : "new",
                    "sound": "notification.wav",
                    "android_channel_id": "stackfood"
                    '.$click_action.'
                  }
            }';
        }

        $ch = curl_init();
        $timeout = 120;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        // Get URL content
        $result = curl_exec($ch);
        // close handle to release resources
        curl_close($ch);

        return $result;
    }

    public static function rating_count($food_id, $rating)
    {
        return Review::where(['food_id' => $food_id, 'rating' => $rating])->count();
    }

    public static function dm_rating_count($deliveryman_id, $rating)
    {
        return DMReview::where(['delivery_man_id' => $deliveryman_id, 'rating' => $rating])->count();
    }

    public static function tax_calculate($food, $price)
    {
        if ($food['tax_type'] == 'percent') {
            $price_tax = ($price / 100) * $food['tax'];
        } else {
            $price_tax = $food['tax'];
        }
        return $price_tax;
    }

    public static function discount_calculate($product, $price)
    {
        if ($product['restaurant_discount']) {
            $price_discount = ($price / 100) * $product['restaurant_discount'];
        } else if ($product['discount_type'] == 'percent') {
            $price_discount = ($price / 100) * $product['discount'];
        } else {
            $price_discount = $product['discount'];
        }
        return $price_discount;
    }

    public static function get_product_discount($product)
    {
        $restaurant_discount = self::get_restaurant_discount($product->restaurant);
        if ($restaurant_discount) {
            $discount = $restaurant_discount['discount'] . ' %';
        } else if ($product['discount_type'] == 'percent') {
            $discount = $product['discount'] . ' %';
        } else {
            $discount = self::format_currency($product['discount']);
        }
        return $discount;
    }

    public static function product_discount_calculate($product, $price, $restaurant)
    {
        $restaurant_discount = self::get_restaurant_discount($restaurant);
        if (isset($restaurant_discount)) {
            $price_discount = ($price / 100) * $restaurant_discount['discount'];
        } else if ($product['discount_type'] == 'percent') {
            $price_discount = ($price / 100) * $product['discount'];
        } else {
            $price_discount = $product['discount'];
        }
        return $price_discount;
    }

    public static function get_price_range($product, $discount = false)
    {
        $lowest_price = $product->price;
        // $highest_price = $product->price;

            // foreach(json_decode($product->variations,true) as $variation){
            //     if(isset($variation["price"])){
            //         foreach (json_decode($product->variations) as $key => $variation) {
            //             if ($lowest_price > $variation->price) {
            //                 $lowest_price = round($variation->price, 2);
            //             }
            //             if ($highest_price < $variation->price) {
            //                 $highest_price = round($variation->price, 2);
            //             }
            //         }
            //         break;
            //     }
            //     else{
            //         foreach ($variation['values'] as $value){
            //             $value['optionPrice'];
            //         }
            //     }
            // }

        if ($discount) {
            $lowest_price -= self::product_discount_calculate($product, $lowest_price, $product->restaurant);
            // $highest_price -= self::product_discount_calculate($product, $highest_price, $product->restaurant);
        }
        $lowest_price = self::format_currency($lowest_price);
        // $highest_price = self::format_currency($highest_price);

        // if ($lowest_price == $highest_price) {
        //     return $lowest_price;
        // }
        // return $lowest_price . ' - ' . $highest_price;
        return $lowest_price;
    }

    public static function get_restaurant_discount($restaurant)
    {
        //dd($restaurant);
        if ($restaurant->discount) {
            if (date('Y-m-d', strtotime($restaurant->discount->start_date)) <= now()->format('Y-m-d') && date('Y-m-d', strtotime($restaurant->discount->end_date)) >= now()->format('Y-m-d') && date('H:i', strtotime($restaurant->discount->start_time)) <= now()->format('H:i') && date('H:i', strtotime($restaurant->discount->end_time)) >= now()->format('H:i')) {
                return [
                    'discount' => $restaurant->discount->discount,
                    'min_purchase' => $restaurant->discount->min_purchase,
                    'max_discount' => $restaurant->discount->max_discount
                ];
            }
        }
        return null;
    }

    public static function max_earning()
    {
        $data = Order::where(['order_status' => 'delivered'])->select('id', 'created_at', 'order_amount')
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)->format('m');
            });

        $max = 0;
        foreach ($data as $month) {
            $count = 0;
            foreach ($month as $order) {
                $count += $order['order_amount'];
            }
            if ($count > $max) {
                $max = $count;
            }
        }
        return $max;
    }

    public static function max_orders()
    {
        $data = Order::select('id', 'created_at')
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)->format('m');
            });

        $max = 0;
        foreach ($data as $month) {
            $count = 0;
            foreach ($month as $order) {
                $count += 1;
            }
            if ($count > $max) {
                $max = $count;
            }
        }
        return $max;
    }

    public static function order_status_update_message($status)
    {
        if ($status == 'pending') {
            $data = BusinessSetting::where('key', 'order_pending_message')->first()->value;
        } elseif ($status == 'confirmed') {
            $data = BusinessSetting::where('key', 'order_confirmation_msg')->first()->value;
        } elseif ($status == 'processing') {
            $data = BusinessSetting::where('key', 'order_processing_message')->first()->value;
        } elseif ($status == 'picked_up') {
            $data = BusinessSetting::where('key', 'out_for_delivery_message')->first()->value;
        } elseif ($status == 'handover') {
            $data = BusinessSetting::where('key', 'order_handover_message')->first()->value;
        } elseif ($status == 'delivered') {
            $data = BusinessSetting::where('key', 'order_delivered_message')->first()->value;
        } elseif ($status == 'delivery_boy_delivered') {
            $data = BusinessSetting::where('key', 'delivery_boy_delivered_message')->first()->value;
        } elseif ($status == 'accepted') {
            $data = BusinessSetting::where('key', 'delivery_boy_assign_message')->first()->value;
        } elseif ($status == 'canceled') {
            $data = BusinessSetting::where('key', 'order_cancled_message')->first()->value;
        } elseif ($status == 'refunded') {
            $data = BusinessSetting::where('key', 'order_refunded_message')->first()->value;
        } elseif ($status == 'refund_request_canceled') {
        $data = BusinessSetting::where('key', 'refund_cancel_message')->first()->value;
        }
        else {
            $data = '{"status":"0","message":""}';
        }

        $res = json_decode($data, true);

        if ($res['status'] == 0) {
            return 0;
        }
        return $res['message'];
    }

    public static function send_order_notification($order)
    {

        try {
            $status = ($order->order_status == 'delivered' && $order->delivery_man) ? 'delivery_boy_delivered' : $order->order_status;
            if($order->order_status=='confirmed' && $order->payment_method != 'cash_on_delivery' && $order->restaurant->restaurant_model == 'subscription' && isset($order->restaurant->restaurant_sub)){
                if ($order->restaurant->restaurant_sub->max_order != "unlimited" && $order->restaurant->restaurant_sub->max_order > 0 ) {
                    $order->restaurant->restaurant_sub->decrement('max_order' , 1);
                }
            }

            if(($order->payment_method == 'cash_on_delivery' && $order->order_status == 'pending' )||($order->payment_method != 'cash_on_delivery' && $order->order_status == 'confirmed' )){
                $data = [
                    'title' => translate('messages.order_push_title'),
                    'description' => translate('messages.new_order_push_description'),
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'new_order_admin',
                ];
                self::send_push_notif_to_topic($data, 'admin_message', 'order_request', url('/').'/admin/order/list/all');
            }

            $value = self::order_status_update_message($status);
            if ($value && $order->customer) {
                $data = [
                    'title' => translate('messages.order_push_title'),
                    'description' => $value,
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'order_status',
                ];
                self::send_push_notif_to_device($order->customer->cm_firebase_token, $data);
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'user_id' => $order->user_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            if($order->customer && $order->order_status == 'refund_request_canceled'){
                $data = [
                    'title' => translate('messages.order_push_title'),
                    'description' => translate('messages.Your_refund_request_has_been_canceled'),
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'order_status',
                ];
                self::send_push_notif_to_device($order->customer->cm_firebase_token, $data);
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'user_id' => $order->user_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            if ($status == 'picked_up') {
                $data = [
                    'title' => translate('messages.order_push_title'),
                    'description' => $value,
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'order_status',
                ];
                self::send_push_notif_to_device($order->restaurant->vendor->firebase_token, $data);
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'vendor_id' => $order->restaurant->vendor_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            if ($order->order_type == 'delivery' && !$order->scheduled && $order->order_status == 'pending' && $order->payment_method == 'cash_on_delivery' && config('order_confirmation_model') == 'deliveryman' && $order->order_type != 'take_away') {
                // if ($order->restaurant->self_delivery_system)
                if ($order->restaurant->restaurant_model == 'commission' && $order->restaurant->self_delivery_system
                || ($order->restaurant->restaurant_model == 'subscription' &&  isset($order->restaurant->restaurant_sub) && $order->restaurant->restaurant_sub->self_delivery)
                )
                {
                    $data = [
                        'title' => translate('messages.order_push_title'),
                        'description' => translate('messages.new_order_push_description'),
                        'order_id' => $order->id,
                        'image' => '',
                        'type' => 'new_order',
                    ];
                    self::send_push_notif_to_device($order->restaurant->vendor->firebase_token, $data);
                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'vendor_id' => $order->restaurant->vendor_id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $web_push_link = url('/').'/restaurant-panel/order/list/all';
                    self::send_push_notif_to_topic($data, "restaurant_panel_{$order->restaurant_id}_message", 'new_order', $web_push_link);
                } else {
                    $data = [
                        'title' => translate('messages.order_push_title'),
                        'description' => translate('messages.new_order_push_description'),
                        'order_id' => $order->id,
                        'image' => '',
                    ];
                    // self::send_push_notif_to_topic($data, $order->restaurant->zone->deliveryman_wise_topic, 'order_request');
                    $topic = 'delivery_man_'.$order->restaurant->zone->id.'_'.$order->vehicle_id;
                    self::send_push_notif_to_topic($data, $topic, 'order_request');
                }
            }

            if ($order->order_type == 'delivery' && !$order->scheduled && $order->order_status == 'pending' && $order->payment_method == 'cash_on_delivery' && config('order_confirmation_model') == 'restaurant') {
                $data = [
                    'title' => translate('messages.order_push_title'),
                    'description' => translate('messages.new_order_push_description'),
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'new_order',
                ];
                self::send_push_notif_to_device($order->restaurant->vendor->firebase_token, $data);
                // self::send_push_notif_to_topic($data, 'admin_message', 'order_request');
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'vendor_id' => $order->restaurant->vendor_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $web_push_link = url('/').'/restaurant-panel/order/list/all';
                self::send_push_notif_to_topic($data, "restaurant_panel_{$order->restaurant_id}_message", 'new_order', $web_push_link);
            }

            if (!$order->scheduled && (($order->order_type == 'take_away' && $order->order_status == 'pending') || ($order->payment_method != 'cash_on_delivery' && $order->order_status == 'confirmed'))) {
                $data = [
                    'title' => translate('messages.order_push_title'),
                    'description' => translate('messages.new_order_push_description'),
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'new_order',
                ];
                self::send_push_notif_to_device($order->restaurant->vendor->firebase_token, $data);
                // self::send_push_notif_to_topic($data, 'admin_message', 'order_request');

                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'vendor_id' => $order->restaurant->vendor_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $web_push_link = url('/').'/restaurant-panel/order/list/all';
                self::send_push_notif_to_topic($data, "restaurant_panel_{$order->restaurant->id}_message", 'new_order', $web_push_link);
            }

            if ($order->order_status == 'confirmed' && $order->order_type != 'take_away' && config('order_confirmation_model') == 'deliveryman' && $order->payment_method == 'cash_on_delivery') {
                if ($order->restaurant->restaurant_model == 'commission' && $order->restaurant->self_delivery_system
                || ($order->restaurant->restaurant_model == 'subscription' &&  isset($order->restaurant->restaurant_sub) && $order->restaurant->restaurant_sub->self_delivery)
                ) {
                    $data = [
                        'title' => translate('messages.order_push_title'),
                        'description' => translate('messages.new_order_push_description'),
                        'order_id' => $order->id,
                        'image' => '',
                    ];

                    self::send_push_notif_to_topic($data, "restaurant_dm_" . $order->restaurant_id, 'new_order');
                    // self::send_push_notif_to_topic($data, 'admin_message', 'order_request');
                } else {
                    $data = [
                        'title' => translate('messages.order_push_title'),
                        'description' => translate('messages.new_order_push_description'),
                        'order_id' => $order->id,
                        'image' => '',
                        'type' => 'new_order',
                    ];
                    self::send_push_notif_to_device($order->restaurant->vendor->firebase_token, $data);
                    // self::send_push_notif_to_topic($data, 'admin_message', 'order_request');
                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'vendor_id' => $order->restaurant->vendor_id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $web_push_link = url('/').'/restaurant-panel/order/list/all';
                    self::send_push_notif_to_topic($data, "restaurant_panel_{$order->restaurant_id}_message", 'new_order', $web_push_link);
                }
            }

            if ($order->order_type == 'delivery' && !$order->scheduled && $order->order_status == 'confirmed'  && ($order->payment_method != 'cash_on_delivery' || config('order_confirmation_model') == 'restaurant')) {
                $data = [
                    'title' => translate('messages.order_push_title'),
                    'description' => translate('messages.new_order_push_description'),
                    'order_id' => $order->id,
                    'image' => '',
                ];
                if ($order->restaurant->restaurant_model == 'commission' && $order->restaurant->self_delivery_system
                || ($order->restaurant->restaurant_model == 'subscription' &&  isset($order->restaurant->restaurant_sub) && $order->restaurant->restaurant_sub->self_delivery)
                )
                {
                    self::send_push_notif_to_topic($data, "restaurant_dm_" . $order->restaurant_id, 'order_request');
                    // self::send_push_notif_to_topic($data, 'admin_message', 'order_request');
                } else {
                    $topic = 'delivery_man_'.$order->restaurant->zone->id.'_'.$order->vehicle_id;
                    self::send_push_notif_to_topic($data, $topic, 'order_request');
                    // self::send_push_notif_to_topic($data, $order->restaurant->zone->deliveryman_wise_topic, 'order_request');
                }
            }

            if (in_array($order->order_status, ['processing', 'handover']) && $order->delivery_man) {
                $data = [
                    'title' => translate('messages.order_push_title'),
                    'description' => $order->order_status == 'processing' ? translate('messages.Proceed_for_cooking') : translate('messages.ready_for_delivery'),
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'order_status'
                ];
                self::send_push_notif_to_device($order->delivery_man->fcm_token, $data);
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'delivery_man_id' => $order->delivery_man->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            try {
                if ($order->order_status == 'confirmed' && $order->payment_method != 'cash_on_delivery' && config('mail.status')) {
                    Mail::to($order->customer->email)->send(new OrderPlaced($order->id));
                }
                if($order->order_status == 'refund_request_canceled' && config('mail.status')){
                    Mail::to($order->customer->email)->send(new RefundRejected($order->id));
                }
            } catch (\Exception $ex) {
                info($ex);
            }
            return true;
        } catch (\Exception $e) {
            info($e);
        }
        return false;
    }

    public static function day_part()
    {
        $part = "";
        $morning_start = date("h:i:s", strtotime("5:00:00"));
        $afternoon_start = date("h:i:s", strtotime("12:01:00"));
        $evening_start = date("h:i:s", strtotime("17:01:00"));
        $evening_end = date("h:i:s", strtotime("21:00:00"));

        if (time() >= $morning_start && time() < $afternoon_start) {
            $part = "morning";
        } elseif (time() >= $afternoon_start && time() < $evening_start) {
            $part = "afternoon";
        } elseif (time() >= $evening_start && time() <= $evening_end) {
            $part = "evening";
        } else {
            $part = "night";
        }

        return $part;
    }

    public static function env_update($key, $value)
    {
        $path = base_path('.env');
        if (file_exists($path)) {
            file_put_contents($path, str_replace(
                $key . '=' . env($key),
                $key . '=' . $value,
                file_get_contents($path)
            ));
        }
    }

    public static function env_key_replace($key_from, $key_to, $value)
    {
        $path = base_path('.env');
        if (file_exists($path)) {
            file_put_contents($path, str_replace(
                $key_from . '=' . env($key_from),
                $key_to . '=' . $value,
                file_get_contents($path)
            ));
        }
    }

    public static  function remove_dir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") Helpers::remove_dir($dir . "/" . $object);
                    else unlink($dir . "/" . $object);
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    public static function get_restaurant_id()
    {
        if (auth('vendor_employee')->check()) {
            return auth('vendor_employee')->user()->restaurant->id;
        }
        return auth('vendor')->user()->restaurants[0]->id;
    }

    public static function get_vendor_id()
    {
        if (auth('vendor')->check()) {
            return auth('vendor')->id();
        } else if (auth('vendor_employee')->check()) {
            return auth('vendor_employee')->user()->vendor_id;
        }
        return 0;
    }

    public static function get_vendor_data()
    {
        if (auth('vendor')->check()) {
            return auth('vendor')->user();
        } else if (auth('vendor_employee')->check()) {
            return auth('vendor_employee')->user()->vendor;
        }
        return 0;
    }

    public static function get_loggedin_user()
    {
        if (auth('vendor')->check()) {
            return auth('vendor')->user();
        } else if (auth('vendor_employee')->check()) {
            return auth('vendor_employee')->user();
        }
        return 0;
    }

    public static function get_restaurant_data()
    {
        if (auth('vendor_employee')->check()) {
            return auth('vendor_employee')->user()->restaurant;
        }
        return auth('vendor')->user()->restaurants[0];
    }

    public static function upload(string $dir, string $format, $image = null)
    {
        if ($image != null) {
            $imageName = \Carbon\Carbon::now()->toDateString() . "-" . uniqid() . "." . $format;
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }
            Storage::disk('public')->put($dir . $imageName, file_get_contents($image));
            return $imageName;
        }
        // else {
        // $imageName = 'def.png';
        // }
        //return $imageName;
    }

    public static function update(string $dir, $old_image, string $format, $image = null)
    {
        if ($image == null) {
            return $old_image;
        }
        if (Storage::disk('public')->exists($dir . $old_image)) {
            Storage::disk('public')->delete($dir . $old_image);
        }
        $imageName = Helpers::upload($dir, $format, $image);
        return $imageName;
    }

    public static function format_coordiantes($coordinates)
    {
        $data = [];
        foreach ($coordinates as $coord) {
            $data[] = (object)['lat' => $coord->getlat(), 'lng' => $coord->getlng()];
        }
        return $data;
    }

    public static function module_permission_check($mod_name)
    {

        if (!auth('admin')->user()->role) {
            return false;
        }

        if ($mod_name == 'zone' && auth('admin')->user()->zone_id) {
            return false;
        }

        $permission = auth('admin')->user()->role->modules;
        if (isset($permission) && in_array($mod_name, (array)json_decode($permission)) == true) {
            return true;
        }

        if (auth('admin')->user()->role_id == 1) {
            return true;
        }
        return false;
    }

    public static function employee_module_permission_check($mod_name)
    {

        if (auth('vendor')->check()) {
            if ($mod_name == 'reviews' ) {
                return auth('vendor')->user()->restaurants[0]->reviews_section ;
            } else if ($mod_name == 'deliveryman') {
                return auth('vendor')->user()->restaurants[0]->self_delivery_system;
            } else if ($mod_name == 'pos') {
                return auth('vendor')->user()->restaurants[0]->pos_system;
            }
            return true;
        } else if (auth('vendor_employee')->check()) {
            $permission = auth('vendor_employee')->user()->role->modules;
            if (isset($permission) && in_array($mod_name, (array)json_decode($permission)) == true) {
                if ($mod_name == 'reviews') {
                    return auth('vendor_employee')->user()->restaurant->reviews_section;
                } else if ($mod_name == 'deliveryman') {
                    return auth('vendor_employee')->user()->restaurant->self_delivery_system;
                } else if ($mod_name == 'pos') {
                    return auth('vendor_employee')->user()->restaurant->pos_system;
                }
                return true;
            }
        }

        return false;


    }


    public static function calculate_addon_price($addons, $add_on_qtys)
    {
        $add_ons_cost = 0;
        $data = [];
        if ($addons) {
            foreach ($addons as $key2 => $addon) {
                if ($add_on_qtys == null) {
                    $add_on_qty = 1;
                } else {
                    $add_on_qty = $add_on_qtys[$key2];
                }
                $data[] = ['id' => $addon->id, 'name' => $addon->name, 'price' => $addon->price, 'quantity' => $add_on_qty];
                $add_ons_cost += $addon['price'] * $add_on_qty;
            }
            return ['addons' => $data, 'total_add_on_price' => $add_ons_cost];
        }
        return null;
    }

    public static function get_settings($name)
    {
        $config = null;
        $data = BusinessSetting::where(['key' => $name])->first();
        if (isset($data)) {
            $config = json_decode($data['value'], true);
            if (is_null($config)) {
                $config = $data['value'];
            }
        }
        return $config;
    }

    public static function setEnvironmentValue($envKey, $envValue)
    {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);
        $oldValue = env($envKey);
        if (strpos($str, $envKey) !== false) {
            $str = str_replace("{$envKey}={$oldValue}", "{$envKey}={$envValue}", $str);
        } else {
            $str .= "{$envKey}={$envValue}\n";
        }
        $fp = fopen($envFile, 'w');
        fwrite($fp, $str);
        fclose($fp);
        return $envValue;
    }

    // public static function requestSender()
    // {
    //     $client = new \GuzzleHttp\Client();
    //     $response = $client->get(route(base64_decode('YWN0aXZhdGlvbi1jaGVjaw==')));
    //     $data = json_decode($response->getBody()->getContents(), true);
    //     return $data;
    // }
    public static function requestSender()
    {
        $class = new LaravelchkController();
        $response = $class->actch();
        return json_decode($response->getContent(), true);
    }


    public static function insert_business_settings_key($key, $value = null)
    {
        $data =  BusinessSetting::where('key', $key)->first();
        if (!$data) {
            DB::table('business_settings')->updateOrInsert(['key' => $key], [
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return true;
    }

    public static function get_language_name($key)
    {
        $languages = array(
            "af" => "Afrikaans",
            "sq" => "Albanian - shqip",
            "am" => "Amharic - አማርኛ",
            "ar" => "Arabic - العربية",
            "an" => "Aragonese - aragonés",
            "hy" => "Armenian - հայերեն",
            "ast" => "Asturian - asturianu",
            "az" => "Azerbaijani - azərbaycan dili",
            "eu" => "Basque - euskara",
            "be" => "Belarusian - беларуская",
            "bn" => "Bengali - বাংলা",
            "bs" => "Bosnian - bosanski",
            "br" => "Breton - brezhoneg",
            "bg" => "Bulgarian - български",
            "ca" => "Catalan - català",
            "ckb" => "Central Kurdish - کوردی (دەستنوسی عەرەبی)",
            "zh" => "Chinese - 中文",
            "zh-HK" => "Chinese (Hong Kong) - 中文（香港）",
            "zh-CN" => "Chinese (Simplified) - 中文（简体）",
            "zh-TW" => "Chinese (Traditional) - 中文（繁體）",
            "co" => "Corsican",
            "hr" => "Croatian - hrvatski",
            "cs" => "Czech - čeština",
            "da" => "Danish - dansk",
            "nl" => "Dutch - Nederlands",
            "en" => "English",
            "en-AU" => "English (Australia)",
            "en-CA" => "English (Canada)",
            "en-IN" => "English (India)",
            "en-NZ" => "English (New Zealand)",
            "en-ZA" => "English (South Africa)",
            "en-GB" => "English (United Kingdom)",
            "en-US" => "English (United States)",
            "eo" => "Esperanto - esperanto",
            "et" => "Estonian - eesti",
            "fo" => "Faroese - føroyskt",
            "fil" => "Filipino",
            "fi" => "Finnish - suomi",
            "fr" => "French - français",
            "fr-CA" => "French (Canada) - français (Canada)",
            "fr-FR" => "French (France) - français (France)",
            "fr-CH" => "French (Switzerland) - français (Suisse)",
            "gl" => "Galician - galego",
            "ka" => "Georgian - ქართული",
            "de" => "German - Deutsch",
            "de-AT" => "German (Austria) - Deutsch (Österreich)",
            "de-DE" => "German (Germany) - Deutsch (Deutschland)",
            "de-LI" => "German (Liechtenstein) - Deutsch (Liechtenstein)",
            "de-CH" => "German (Switzerland) - Deutsch (Schweiz)",
            "el" => "Greek - Ελληνικά",
            "gn" => "Guarani",
            "gu" => "Gujarati - ગુજરાતી",
            "ha" => "Hausa",
            "haw" => "Hawaiian - ʻŌlelo Hawaiʻi",
            "he" => "Hebrew - עברית",
            "hi" => "Hindi - हिन्दी",
            "hu" => "Hungarian - magyar",
            "is" => "Icelandic - íslenska",
            "id" => "Indonesian - Indonesia",
            "ia" => "Interlingua",
            "ga" => "Irish - Gaeilge",
            "it" => "Italian - italiano",
            "it-IT" => "Italian (Italy) - italiano (Italia)",
            "it-CH" => "Italian (Switzerland) - italiano (Svizzera)",
            "ja" => "Japanese - 日本語",
            "kn" => "Kannada - ಕನ್ನಡ",
            "kk" => "Kazakh - қазақ тілі",
            "km" => "Khmer - ខ្មែរ",
            "ko" => "Korean - 한국어",
            "ku" => "Kurdish - Kurdî",
            "ky" => "Kyrgyz - кыргызча",
            "lo" => "Lao - ລາວ",
            "la" => "Latin",
            "lv" => "Latvian - latviešu",
            "ln" => "Lingala - lingála",
            "lt" => "Lithuanian - lietuvių",
            "mk" => "Macedonian - македонски",
            "ms" => "Malay - Bahasa Melayu",
            "ml" => "Malayalam - മലയാളം",
            "mt" => "Maltese - Malti",
            "mr" => "Marathi - मराठी",
            "mn" => "Mongolian - монгол",
            "ne" => "Nepali - नेपाली",
            "no" => "Norwegian - norsk",
            "nb" => "Norwegian Bokmål - norsk bokmål",
            "nn" => "Norwegian Nynorsk - nynorsk",
            "oc" => "Occitan",
            "or" => "Oriya - ଓଡ଼ିଆ",
            "om" => "Oromo - Oromoo",
            "ps" => "Pashto - پښتو",
            "fa" => "Persian - فارسی",
            "pl" => "Polish - polski",
            "pt" => "Portuguese - português",
            "pt-BR" => "Portuguese (Brazil) - português (Brasil)",
            "pt-PT" => "Portuguese (Portugal) - português (Portugal)",
            "pa" => "Punjabi - ਪੰਜਾਬੀ",
            "qu" => "Quechua",
            "ro" => "Romanian - română",
            "mo" => "Romanian (Moldova) - română (Moldova)",
            "rm" => "Romansh - rumantsch",
            "ru" => "Russian - русский",
            "gd" => "Scottish Gaelic",
            "sr" => "Serbian - српски",
            "sh" => "Serbo-Croatian - Srpskohrvatski",
            "sn" => "Shona - chiShona",
            "sd" => "Sindhi",
            "si" => "Sinhala - සිංහල",
            "sk" => "Slovak - slovenčina",
            "sl" => "Slovenian - slovenščina",
            "so" => "Somali - Soomaali",
            "st" => "Southern Sotho",
            "es" => "Spanish - español",
            "es-AR" => "Spanish (Argentina) - español (Argentina)",
            "es-419" => "Spanish (Latin America) - español (Latinoamérica)",
            "es-MX" => "Spanish (Mexico) - español (México)",
            "es-ES" => "Spanish (Spain) - español (España)",
            "es-US" => "Spanish (United States) - español (Estados Unidos)",
            "su" => "Sundanese",
            "sw" => "Swahili - Kiswahili",
            "sv" => "Swedish - svenska",
            "tg" => "Tajik - тоҷикӣ",
            "ta" => "Tamil - தமிழ்",
            "tt" => "Tatar",
            "te" => "Telugu - తెలుగు",
            "th" => "Thai - ไทย",
            "ti" => "Tigrinya - ትግርኛ",
            "to" => "Tongan - lea fakatonga",
            "tr" => "Turkish - Türkçe",
            "tk" => "Turkmen",
            "tw" => "Twi",
            "uk" => "Ukrainian - українська",
            "ur" => "Urdu - اردو",
            "ug" => "Uyghur",
            "uz" => "Uzbek - o‘zbek",
            "vi" => "Vietnamese - Tiếng Việt",
            "wa" => "Walloon - wa",
            "cy" => "Welsh - Cymraeg",
            "fy" => "Western Frisian",
            "xh" => "Xhosa",
            "yi" => "Yiddish",
            "yo" => "Yoruba - Èdè Yorùbá",
            "zu" => "Zulu - isiZulu",
        );
        return array_key_exists($key, $languages) ? $languages[$key] : $key;
    }

    public static function get_view_keys()
    {
        $keys = BusinessSetting::whereIn('key', ['toggle_veg_non_veg', 'toggle_dm_registration', 'toggle_restaurant_registration'])->get();
        $data = [];
        foreach ($keys as $key) {
            $data[$key->key] = (bool)$key->value;
        }
        return $data;
    }

    public static function default_lang()
    {
        // if (strpos(url()->current(), '/api')) {
        //     $lang = App::getLocale();
        // } elseif (session()->has('local')) {
        //     $lang = session('local');
        // } else {
        //     $data = Helpers::get_business_settings('language');
        //     $code = 'en';
        //     $direction = 'ltr';
        //     foreach ($data as $ln) {
        //         if (array_key_exists('default', $ln) && $ln['default']) {
        //             $code = $ln['code'];
        //             if (array_key_exists('direction', $ln)) {
        //                 $direction = $ln['direction'];
        //             }
        //         }
        //     }
        //     session()->put('local', $code);
        //     Session::put('direction', $direction);
        //     $lang = $code;
        // }
        // return $lang;
        return 'en';
    }
    // public static function generate_referer_code($user)
    // {
    //     $user_name = $user_name = explode('@',$user->email)[0];
    //     $user_id = $user->id;
    //     //dd($user_id);
    //     $uid_length = strlen($user->id);
    //     if (strlen($user_name) > 10 - $uid_length) {
    //         $user_name = substr($user_name, 0, 10 - $uid_length);
    //     } else if (strlen($user_name) < 10 - $uid_length) {
    //         $user_id = $user_id * pow(10, ((10 - $uid_length) - strlen($user_name)));
    //     }
    //     return $user_name . $user_id;
    // }


    public static function generate_referer_code() {
        $ref_code = strtoupper(Str::random(10));

        if (self::referer_code_exists($ref_code)) {
            return self::generate_referer_code();
        }

        return $ref_code;
    }

    public static function referer_code_exists($ref_code) {
        return User::where('ref_code', '=', $ref_code)->exists();
    }



    public static function remove_invalid_charcaters($str)
    {
        return str_ireplace(['\'', '"', ',', ';', '<', '>', '?'], ' ', $str);
    }

    public static function set_time_log($user_id , $date, $online = null, $offline = null)
    {
        try {
            $time_log = TimeLog::where(['user_id'=>$user_id, 'date'=>$date])->first();

            if($time_log && $time_log->online && $online) return true;

            if($offline && $time_log) {
                $time_log->offline = $offline;
                $time_log->working_hour = (strtotime($offline) - strtotime($time_log->online))/60;
                $time_log->save();
                return true;
            }

            $time_log = new TimeLog;
            $time_log->date = $date;
            $time_log->user_id = $user_id;
            $time_log->offline = $offline;
            $time_log->online = $online;
            $time_log->save();
            return true;
        } catch(\Exception $ex) {
            info($ex);
        }
        return false;
    }

    public static function push_notification_export_data($data){
        $format = [];
        foreach($data as $key=>$item){
            $format[] =[
                '#'=>$key+1,
                translate('title')=>$item['title'],
                translate('description')=>$item['description'],
                translate('zone')=>$item->zone ? $item->zone->name : translate('messages.all_zones'),
                translate('tergat')=>$item['tergat'],
                translate('status')=>$item['status']
            ];
        }
        return $format;
    }
    public static function restaurant_withdraw_list_export($data){
        $format = [];
        foreach($data as $key=>$wr){

            if($wr->approved==0){
            $status=translate('Pending');
            }elseif($wr->approved==1){
                $status= translate('Approved');
            } else{
                $status=  translate('Denied');
            }

            $format[] =[
                'Sl'=>$key+1,
                translate('amount')=>$wr['amount'],
                translate('restaurant')=>$wr->vendor?$wr->vendor->restaurants[0]->name:translate('messages.Restaurant deleted!'),
                translate('request_time')=>date('Y-m-d '.config('timeformat'),strtotime($wr->created_at)),
                translate('status')=>$status,
            ];
        }
        return $format;
    }
    public static function vendor_employee_list_export($data){
        $format = [];
        foreach($data as $key=>$e){
            $format[] =[
                'Sl'=>$key+1,
                translate('name')=>$e['f_name'].' '.$e['l_name'] ,
                translate('email')=>$e['email'],
                translate('phone')=>$e['phone'],
                translate('Role')=>$e->role?$e->role['name']:translate('messages.role_deleted'),
            ];
        }
        return $format;
    }
    public static function dm_earning_list_export($data){
        $format = [];
        foreach($data as $key=>$at){
            $format[] =[
                'Sl'=>$key+1,
                translate('name')=>$at->delivery_man ? $at->delivery_man->f_name.' '.$at->delivery_man->l_name : translate('messages.deliveryman_deleted!') ,
                translate('received_at')=>$at->created_at->format('Y-m-d '.config('timeformat')),
                translate('amount')=>$at['amount'],
                translate('method')=>$at['method'],
                translate('reference')=>$at['ref'],
            ];
        }
        return $format;
    }
    public static function export_account_transaction($data){
        $format = [];
        foreach($data as $key=>$at){

            if($at->restaurant){
               $received_from= $at->restaurant->name;
            } elseif($at->deliveryman){
              $received_from=   $at->deliveryman->f_name .' '. $at->deliveryman->l_name;
            }
             else {
               $received_from=  translate('messages.not_found');
             }

            $format[] =[
                'Sl'=>$key+1,
                translate('received_from')=> $received_from,
                translate('type')=>$at['from_type'],
                translate('received_at')=>$at->created_at->format('Y-m-d '.config('timeformat')),
                translate('amount')=>$at['amount'],
                translate('reference')=>$at['ref'],
            ];
        }
        return $format;
    }



    public static function export_zones($collection){
        $data = [];

        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.zone').' '.translate('messages.id')=>$item['id'],
                translate('messages.name')=>$item['name'],
                translate('messages.restaurants')=> $item->restaurants->count(),
                translate('messages.deliveryman')=>  $item->deliverymen->count(),
                translate('messages.status')=> $item['status']
            ];
        }

        return $data;
    }

    public static function export_restaurants($collection){
        $data = [];

        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.restaurant_name')=> $item['name'],
                translate('messages.owner_information') => $item->vendor->f_name.' '.$item->vendor->l_name,
                translate('messages.phone') => $item->vendor->phone,
                translate('messages.zone') => $item->zone->name,
                translate('messages.status') => $item['status'] ? translate('messages.active') : translate('messages.inactive'),
            ];
        }

        return $data;
    }
    public static function export_subscription($collection){
        $data = [];

        foreach($collection as $key=>$item){
            $payment_status =translate('paid');
        if ($item->payment_method == 'free_trial'){
            $payment_status =translate('unpaid');
            $Payment_type= translate('messages.free_trial') ;
        }
        elseif($item->payment_method == 'wallet'){
            $Payment_type= translate('messages.Wallet payment');
        }
        elseif($item->payment_method == 'manual_payment_admin'){
            $Payment_type= translate('messages.Manual payment') ;
        }
        elseif($item->payment_method == 'manual_payment_by_restaurant'){
            $Payment_type= translate('messages.Manual payment') ;
        }else{
            $Payment_type= $item->payment_method ;
        }

            $data[] = [
                'SL'=>$key+1,
                translate('messages.transaction').' '.translate('messages.id') => $item->id,
                translate('Transaction Date')=> $item->created_at->format('d M Y'),
                translate('messages.restaurant_name') => $item->restaurant->name,
                translate('messages.Package_name') =>    $item->package->package_name,
                translate('messages.Pricing') => Helpers::format_currency($item->price) ,
                translate('messages.Duration') => $item->validity . ' ' . translate('messages.days'),
                translate('messages.Payment Status') => $payment_status,
                translate('messages.Payment_type') => $Payment_type,
            ];
        }

        return $data;
    }

    public static function export_restaurant_orders($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.order_id') => $item['id'],
                translate('messages.order_date') => $item['created_at'],
                translate('messages.customer_name') => isset($item->customer) ? $item->customer->f_name.' '.$item->customer->l_name : null,
                translate('messages.phone') => isset($item->customer) ? $item->customer->phone : null,
                translate('messages.total_amount') => $item['order_amount'].' '.Helpers::currency_symbol(),
                translate('messages.order_status') => $item['order_status']
            ];
        }
        return $data;
    }
    public static function export_d_man($collection){
        $availability='';
        foreach($collection as $key=>$dm){
            if($dm->application_status == 'approved'){
                if($dm->active){
                    $availability=translate('messages.online');
                }else{
                $availability=translate('messages.offline');
                }
            }
            elseif($dm->application_status == 'denied'){
                $availability=translate('messages.denied');
            } else{
                $availability=translate('messages.pending');
            }

            $data[] = [
                'SL'=>$key+1,
                translate('messages.name') => $dm['f_name'].' '.$dm['l_name'],
                translate('messages.ratings') => count($dm->rating)>0?number_format($dm->rating[0]->average, 1, '.', ' '):0,
                translate('messages.contact') => $dm['phone'],
                translate('messages.zone') => isset($dm->zone) ? $dm->zone->name : translate('messages.zone').' '.translate('messages.deleted'),
                translate('messages.Total Orders') =>  $dm->orders ? count($dm->orders):0 ,
                translate('messages.Currenty Assigned Orders') => $dm->current_orders ,
                translate('messages.availability') => $availability ,
            ];
        }
        return $data;
    }




    public static function export_day_wise_report($collection){
        $data = [];

        foreach($collection as $key=>$item){
            $discount_by_admin = 0;
            if($item->order->discount_on_product_by == 'admin'){
                $discount_by_admin = $item->order['restaurant_discount_amount'];
            };
            $data[] = [
                'SL'=>$key+1,
                translate('messages.order_id') => $item['order_id'],
                translate('messages.restaurant')=>$item->order->restaurant?$item->order->restaurant->name:translate('messages.invalid'),
                translate('messages.customer_name')=>$item->order->customer?$item->order->customer['f_name'].' '.$item->order->customer['l_name']:translate('messages.invalid').' '.translate('messages.customer').' '.translate('messages.data'),
                translate('total_item_amount')=>\App\CentralLogics\Helpers::format_currency($item->order['order_amount'] - $item->order['dm_tips']-$item->order['delivery_charge'] - $item['tax'] + $item->order['coupon_discount_amount'] + $item->order['restaurant_discount_amount']),
                translate('item_discount')=>\App\CentralLogics\Helpers::format_currency($item->order->details->sum('discount_on_food')),
                translate('coupon_discount')=>\App\CentralLogics\Helpers::format_currency($item->order['coupon_discount_amount']),
                translate('discounted_amount')=>\App\CentralLogics\Helpers::format_currency($item->order['coupon_discount_amount'] + $item->order['restaurant_discount_amount']),
                translate('messages.tax')=>\App\CentralLogics\Helpers::format_currency($item->tax),
                translate('messages.delivery_charge')=>\App\CentralLogics\Helpers::format_currency($item['delivery_charge'] + $item['delivery_fee_comission']),
                translate('messages.total_order_amount') => \App\CentralLogics\Helpers::format_currency($item['order_amount']),
                translate('messages.admin_discount') => \App\CentralLogics\Helpers::format_currency($item['admin_expense']),
                translate('messages.restaurant_discount') => \App\CentralLogics\Helpers::format_currency($item->discount_amount_by_restaurant),
                translate('messages.admin_commission') => \App\CentralLogics\Helpers::format_currency($item->admin_commission + $item->admin_expense - $discount_by_admin ),
                translate('Comission on delivery fee') => \App\CentralLogics\Helpers::format_currency($item['delivery_fee_comission']),
                translate('admin_net_income') => \App\CentralLogics\Helpers::format_currency($item['admin_commission'] + $item->delivery_fee_comission),
                translate('restaurant_net_income') => \App\CentralLogics\Helpers::format_currency($item['restaurant_amount'] - $item['tax']),
                translate('messages.amount_received_by') => $item['received_by'],
                translate('messages.payment_method')=>translate(str_replace('_', ' ', $item->order['payment_method'])),
                translate('messages.payment_status') => $item->status ? translate("messages.refunded") : translate("messages.completed"),
            ];
        }

        return $data;
    }
    public static function food_wise_report_export($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                 translate('messages.id') => $item['id'],
                 translate('messages.name') => $item['name'],
                 translate('messages.restaurant') => $item->restaurant ? $item->restaurant->name : '',
                 translate('messages.order') => $item->orders_count,
                 translate('messages.price') => \App\CentralLogics\Helpers::format_currency($item->price),
                 translate('messages.total_amount_sold') => \App\CentralLogics\Helpers::format_currency($item->orders_sum_price),
                 translate('messages.total_discount_given') => \App\CentralLogics\Helpers::format_currency($item->orders_sum_discount_on_food),
                 translate('messages.average_sale_value') => $item->orders_count>0? \App\CentralLogics\Helpers::format_currency(($item->orders_sum_price-$item->orders_sum_discount_on_food)/$item->orders_count):0 ,
                 translate('messages.average_ratings') => round($item->avg_rating,1),
            ];
        }
        return $data;
    }




    public static function export_restaurant_food($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                translate('messages.name') => $item['name'],
                translate('messages.category') => $item->category,
                translate('messages.price') => $item['price'],
                translate('messages.status') => $item['status']
            ];
        }

        return $data;
    }

    public static function export_categories($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                 translate('messages.id') => $item['id'],
                 translate('messages.name') => $item['name'],
                 translate('messages.priority') => ($item['priority'] == 1) ? 'medium' : ((1)? 'normal' : 'high'),
                 translate('messages.status') => $item['status']
            ];
        }

        return $data;
    }

    public static function export_attributes($collection){
        $data = [];
        foreach($collection as $key=>$item){
            $data[] = [
                'SL'=>$key+1,
                 translate('messages.id') => $item['id'],
                 translate('messages.name') => $item['name'],
            ];
        }

        return $data;
    }

    public static function get_varient(array $product_variations, array $variations)
    {
        $result = [];
        $variation_price = 0;

        foreach($variations as $k=> $variation){
            foreach($product_variations as  $product_variation){
                if( isset($variation['values']) && isset($product_variation['values']) && $product_variation['name'] == $variation['name']  ){
                    $result[$k] = $product_variation;
                    $result[$k]['values'] = [];
                    foreach($product_variation['values'] as $key=> $option){
                        if(in_array($option['label'], $variation['values']['label'])){
                            $result[$k]['values'][] = $option;
                            $variation_price += $option['optionPrice'];
                        }
                    }
                }
            }
        }

        return ['price'=>$variation_price,'variations'=>$result];
    }




    public Static function subscription_check()
    {
        $business_model= BusinessSetting::where('key', 'business_model')->first();
        if(!$business_model)
            {
                Helpers::insert_business_settings_key('refund_active_status', '1');
                Helpers::insert_business_settings_key('business_model',
                json_encode([
                    'commission'        =>  1,
                    'subscription'     =>  0,
                ]));
                $business_model = [
                    'commission'        =>  1,
                    'subscription'     =>  0,
                ];
            } else{
                $business_model = $business_model->value ? json_decode($business_model->value, true) : [
                    'commission'        =>  1,
                    'subscription'     =>  0,
                ];
            }

        if ($business_model['subscription'] == 1 ){
            return true;
        }
        return false;
    }

    public Static function commission_check()
    {
        $business_model= BusinessSetting::where('key', 'business_model')->first();
        if(!$business_model)
            {
                Helpers::insert_business_settings_key('business_model',
                json_encode([
                    'commission'        =>  1,
                    'subscription'     =>  0,
                ]));
                $business_model = [
                    'commission'        =>  1,
                    'subscription'     =>  0,
                ];
            } else{
                $business_model = $business_model->value ? json_decode($business_model->value, true) : [
                    'commission'        =>  1,
                    'subscription'     =>  0,
                ];
            }

        if ($business_model['commission'] == 1 ){
            return true;
        }
        return false;
    }

    public static function check_subscription_validity()
    {
        $current_date = date('Y-m-d');
        $check_subscription_validity_on= BusinessSetting::where('key', 'check_subscription_validity_on')->first();
        if(!$check_subscription_validity_on){
            Helpers::insert_business_settings_key('check_subscription_validity_on', date('Y-m-d'));
        }
        if($check_subscription_validity_on && $check_subscription_validity_on->value != $current_date){
            Restaurant::whereHas('restaurant_subs',function ($query)use($current_date){
                $query->where('status',1)->where('expiry_date', '<', $current_date);
            })->update(['status' => 0,
                        'pos_system'=>1,
                        'self_delivery_system'=>1,
                        'reviews_section'=>1,
                        'free_delivery'=>0,
                        'restaurant_model'=>'unsubscribed',
                        ]);
            RestaurantSubscription::where('status',1)->where('expiry_date', '<', $current_date)->update([
                'status' => 0
            ]);
            $check_subscription_validity_on->value=$current_date;
            $check_subscription_validity_on->save();
        }
        return false;
    }

    public static function subscription_plan_chosen($restaurant_id ,$package_id, $payment_method  ,$discount,$reference=null ,$type=null){
        $restaurant=Restaurant::findOrFail($restaurant_id);
        $package = SubscriptionPackage::findOrFail($package_id);
        $add_days=0;
        $add_orders=0;
        $total_food= $restaurant->foods()->withoutGlobalScope(\App\Scopes\RestaurantScope::class)->count();
        if ($package->max_product != 'unlimited' &&  $total_food >= $package->max_product  ){
            return 'downgrade_error';
        }
        try {
            $restaurant_subscription=$restaurant->restaurant_sub;
            if (isset($restaurant_subscription) && $type == 'renew') {
                $restaurant_subscription->total_package_renewed= $restaurant_subscription->total_package_renewed + 1;
                $day_left=$restaurant_subscription->expiry_date->format('Y-m-d');
                if (Carbon::now()->subDays(1)->diffInDays($day_left, false) > 0) {
                    $add_days= Carbon::now()->subDays(1)->diffInDays($day_left, false);
                }
                if ($restaurant_subscription->max_order != 'unlimited' && $restaurant_subscription->max_order > 0) {
                    $add_orders=$restaurant_subscription->max_order;
                }
            } else{
                RestaurantSubscription::where('restaurant_id',$restaurant->id)->update([
                    'status' => 0,
                ]);
                $restaurant_subscription =new RestaurantSubscription();
                $restaurant_subscription->total_package_renewed= 0;

            }

            $restaurant_subscription->package_id=$package->id;
            $restaurant_subscription->restaurant_id=$restaurant->id;
            if ($payment_method  == 'free_trial' ) {
                $free_trial_period_data = BusinessSetting::where(['key' => 'free_trial_period'])->first();
                if ($free_trial_period_data == false) {
                    $values= [
                        'data' => 7,
                        'status' => 1,
                    ];
                    Helpers::insert_business_settings_key('free_trial_period',  json_encode($values) );
                }
                $free_trial_period_data = json_decode(BusinessSetting::where(['key' => 'free_trial_period'])->first()->value,true);
                $free_trial_period= $free_trial_period_data['data'];
                $restaurant_subscription->expiry_date= Carbon::now()->addDays($free_trial_period)->format('Y-m-d');
            }
            else{
                $restaurant_subscription->expiry_date= Carbon::now()->addDays($package->validity+$add_days)->format('Y-m-d');
            }
            if($package->max_order != 'unlimited'){
                $restaurant_subscription->max_order=$package->max_order + $add_orders;
            } else{
                $restaurant_subscription->max_order=$package->max_order;
            }


            $restaurant_subscription->max_product=$package->max_product;
            $restaurant_subscription->pos=$package->pos;
            $restaurant_subscription->mobile_app=$package->mobile_app;
            $restaurant_subscription->chat=$package->chat;
            $restaurant_subscription->review=$package->review;
            $restaurant_subscription->self_delivery=$package->self_delivery;

            $restaurant->food_section= 1;
            $restaurant->pos_system= 1;
            if ($type == 'new_join') {
                $restaurant->status= 0;
                $restaurant_subscription->status= 0;

            }else{
                $restaurant->status= 1;
                $restaurant_subscription->status= 1;

            }

            // For Restaurant Free Delivery
            if($restaurant->free_delivery == 1 && $package->self_delivery == 1){
                $restaurant->free_delivery = 1 ;
            } else{
                $restaurant->free_delivery = 0 ;
                $restaurant->coupon()->where('created_by','vendor')->where('coupon_type','free_delivery')->delete();
            }


            $restaurant->reviews_section= 1;
            $restaurant->self_delivery_system= 1;
            $restaurant->restaurant_model= 'subscription';

            $subscription_transaction= new SubscriptionTransaction();
            $subscription_transaction->id= Str::uuid();
            $subscription_transaction->package_id=$package->id;
            $subscription_transaction->restaurant_id=$restaurant->id;
            $subscription_transaction->price=$package->price;
            if ($payment_method  == 'free_trial') {
                $subscription_transaction->validity= $free_trial_period;
                $subscription_transaction->paid_amount= 0;
            } else {
                $subscription_transaction->validity=$package->validity;
                $subscription_transaction->paid_amount= $package->price - (($package->price*$discount)/100);
            }
            $subscription_transaction->payment_method=$payment_method;
            $subscription_transaction->reference=$reference ?? null;
            $subscription_transaction->discount=$discount ?? 0;
            if( $payment_method == 'manual_payment_admin'){
                $subscription_transaction->created_by= 'Admin';
            } else{
                $subscription_transaction->created_by= 'Restaurant';
            }

            $subscription_transaction->package_details=[
                'pos'=>$package->pos,
                'review'=>$package->review,
                'self_delivery'=>$package->self_delivery,
                'chat'=>$package->chat,
                'mobile_app'=>$package->mobile_app,
                'max_order'=>$package->max_order,
                'max_product'=>$package->max_product,
            ];

            DB::beginTransaction();
            $restaurant->save();
            $subscription_transaction->save();
            $restaurant_subscription->save();
            DB::commit();
        } catch(\Exception $e){
            DB::rollBack();
            info(["line___{$e->getLine()}",$e->getMessage()]);
            return false;
        }
        return true;
    }
    public static function expenseCreate($amount,$type,$datetime,$order_id,$created_by,$restaurant_id=null,$description='')
    {
        $expense = new Expense();
        $expense->amount = $amount;
        $expense->type = $type;
        $expense->order_id = $order_id;
        $expense->created_by = $created_by;
        $expense->restaurant_id = $restaurant_id;
        $expense->description = $description;
        $expense->created_at = $datetime;
        $expense->updated_at = $datetime;
        return $expense->save();
    }
    public static function hex_to_rbg($color){
        list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
        $output = "$r, $g, $b";
        return $output;
    }

    public static function increment_order_count($data){
        $restaurant=$data;
        $rest_sub=$restaurant->restaurant_sub;
        if ( $restaurant->restaurant_model == 'subscription' && isset($rest_sub) && $rest_sub->max_order != "unlimited") {
            $rest_sub->increment('max_order', 1);
        }
        return true;
    }

    public static function react_activation_check($react_domain, $react_license_code){
        $scheme = str_contains($react_domain, 'localhost')?'http://':'https://';
        $url = empty(parse_url($react_domain)['scheme']) ? $scheme . ltrim($react_domain, '/') : $react_domain;
        $response = Http::post('https://store.6amtech.com/api/v1/customer/license-check', [
            'domain_name' => str_ireplace('www.', '', parse_url($url, PHP_URL_HOST)),
            'license_code' => $react_license_code
        ]);
        return ($response->successful() && isset($response->json('content')['is_active']) && $response->json('content')['is_active']);
    }

    public static function activation_submit($purchase_key)
    {
        $post = [
            'purchase_key' => $purchase_key
        ];
        $live = 'https://check.6amtech.com';
        $ch = curl_init($live . '/api/v1/software-check');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $response = curl_exec($ch);
        curl_close($ch);
        $response_body = json_decode($response, true);

        try {
            if ($response_body['is_valid'] && $response_body['result']['item']['id'] == env('REACT_APP_KEY')) {
                $previous_active = json_decode(BusinessSetting::where('key', 'app_activation')->first()->value ?? '[]');
                $found = 0;
                foreach ($previous_active as $key => $item) {
                    if ($item->software_id == env('REACT_APP_KEY')) {
                        $found = 1;
                    }
                }
                if (!$found) {
                    $previous_active[] = [
                        'software_id' => env('REACT_APP_KEY'),
                        'is_active' => 1
                    ];
                    DB::table('business_settings')->updateOrInsert(['key' => 'app_activation'], [
                        'value' => json_encode($previous_active)
                    ]);
                }
                return true;
            }

        } catch (\Exception $exception) {
            info($exception->getMessage());
        }
        return false;
    }

    public static function react_domain_status_check(){
        $data = self::get_business_settings('react_setup');
        if($data && isset($data['react_domain']) && isset($data['react_license_code'])){
            if(isset($data['react_platform']) && $data['react_platform'] == 'codecanyon'){
                $data['status'] = (int)self::activation_submit($data['react_license_code']);
            }elseif(!self::react_activation_check($data['react_domain'], $data['react_license_code'])){
                $data['status']=0;
            }elseif($data['status'] != 1){
                $data['status']=1;
            }
            DB::table('business_settings')->updateOrInsert(['key' => 'react_setup'], [
                'value' => json_encode($data)
            ]);
        }
    }

    public static function number_format_short( $n ) {
        if ($n < 900) {
            // 0 - 900
            $n = $n;
            $suffix = '';
        } else if ($n < 900000) {
            // 0.9k-850k
            $n = $n / 1000;
            $suffix = 'K';
        } else if ($n < 900000000) {
            // 0.9m-850m
            $n = $n / 1000000;
            $suffix = 'M';
        } else if ($n < 900000000000) {
            // 0.9b-850b
            $n = $n / 1000000000;
            $suffix = 'B';
        } else {
            // 0.9t+
            $n = $n / 1000000000000;
            $suffix = 'T';
        }

        // if(!session()->has('currency_symbol_position')){
        //     $currency_symbol_position = BusinessSetting::where(['key' => 'currency_symbol_position'])->first()->value;
        //     session()->put('currency_symbol_position',$currency_symbol_position);
        // }

        $currency_symbol_position = BusinessSetting::where(['key' => 'currency_symbol_position'])->first()->value;

        return $currency_symbol_position == 'right' ? number_format($n, config('round_up_to_digit')).$suffix . ' ' . self::currency_symbol() : self::currency_symbol() . ' ' . number_format($n, config('round_up_to_digit')).$suffix;
    }


    public static function gen_mpdf($view, $file_prefix, $file_postfix)
    {
        $mpdf = new \Mpdf\Mpdf(['tempDir' => __DIR__ . '/../../storage/tmp','default_font' => 'FreeSerif', 'mode' => 'utf-8', 'format' => [190, 250]]);
        /* $mpdf->AddPage('XL', '', '', '', '', 10, 10, 10, '10', '270', '');*/
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        $mpdf_view = $view;
        $mpdf_view = $mpdf_view->render();
        $mpdf->WriteHTML($mpdf_view);
        $mpdf->Output($file_prefix . $file_postfix . '.pdf', 'D');
    }

    public static function export_expense_wise_report($collection){
        $data = [];
        foreach($collection as $key=>$item){
            if(isset($item->order->customer)){
                            $customer_name= $item->order->customer->f_name.' '.$item->order->customer->l_name;
                                }
            $data[] = [
                'SL'=>$key+1,
                translate('messages.order_id') => $item['order_id'],
                translate('messages.expense_date') =>  $item['created_at']->format('Y/m/d ' . config('timeformat')),
                 translate('messages.type') => str::title( str_replace('_', ' ',  $item['type'])),
                 translate('messages.customer_name') => $customer_name,
                 translate('messages.amount') => $item['amount'],
            ];
        }
        return $data;
    }

    public static function product_tax($price , $tax, $is_include=false){
        $price_tax = ($price * $tax) / (100 + ($is_include?$tax:0)) ;
        return $price_tax;
    }
}
