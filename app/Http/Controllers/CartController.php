<?php

namespace App\Http\Controllers;

use App\Helpers\CartHelper;
use App\Helpers\SettingHelper;
use App\Models\PointTransaction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CartController extends Controller
{
    public function index()
    {
        return view('cart');
    }

    public function insert(Request $request)
    {
        $product = Product::findorFail($request->productid);
        $price = (SettingHelper::getSettingValueBySLug('gst_charges')) ?
        ceil($product->price + $product->price / SettingHelper::getSettingValueBySLug('gst_charges')) : $product->price;
        if ($request->discount_coupon) {
            $price = $price - ($price * (20 / 100));
        }
        $item = \Cart::session('normal')->get($request->productid);
        if ($item) {
            $stock = $request->quantity + $item->quantity;
            if ($product->stock < $stock) {
                $json = ['type' => 0, 'msg' => 'Cart is out of stock'];
                return response()->json($json);
            }
            $item = \Cart::session('normal')->get($request->productid);
            $item->attributes->put('product_points', $item->attributes->product_points * $request->quantity);
            $item->attributes->put('product_weight', $item->attributes->product_weight * $request->quantity);
            \Cart::session('normal')->update($request->productid, $item);

            $response = \Cart::session('normal')->update($request->productid, array(
                'quantity' => array(
                    // 'relative' => false,
                    'value' => $request->quantity,
                ),
            ));

        } else {
            $stock = $request->quantity;
            if ($product->stock < $stock) {
                $json = ['type' => 0, 'msg' => 'Cart is out of stock'];
                return response()->json($json);
            }
            $response = \Cart::session('normal')->add([
                'id' => $product->id,
                'name' => $product->product,
                'quantity' => $request->quantity,
                'price' => $price,
                'attributes' => array(
                    'product_discount' => 0,
                    // 'product_price' => $request->quantity * $product->price,
                    'product_price' => $request->quantity * $price,
                    'product_points' => $request->quantity * $product->points,
                    'product_weight' => $request->quantity * $product->weight,
                    'product_image' => ($product->image) ? $product->image : null,
                ),
            ]);
        }

        if ($response) {
            $json = ['type' => 1, 'msg' => 'Product is added into cart'];
        } else {
            $json = ['type' => 0, 'msg' => 'Something went wrong'];
        }
        return response()->json($json);
    }

    public function insertDiscount(Request $request)
    {
        if (Auth::guard('web')->check()) {
            if (CartHelper::cartDiscountCount(Auth::guard('web')->user()->id)) {
                $json = ['type' => 0, 'msg' => 'Reached Monthly Discount Product Limit'];
                return response()->json($json);
            }

            $point = PointTransaction::select(DB::raw("SUM(point) as count"))
                ->where('user_id', Auth::guard('web')->user()->id)
                ->where('status', 1)
                ->where('is_child', 0)
                ->first();
            if ($point < 100) {
                $json = ['type' => 0, 'msg' => 'Total Point should be greater than 100'];
                return response()->json($json);
            }

        } else {
            $json = ['type' => 0, 'msg' => 'Please Login To Add Discount Product'];
            return response()->json($json);
        }

        $product = Product::findorFail($request->productid);
        $price = (SettingHelper::getSettingValueBySLug('gst_charges')) ?
        ceil($product->price + $product->price / SettingHelper::getSettingValueBySLug('gst_charges')) : $product->price;
        $price = $price - ($product->price * ($product->discount / 100));

        $countDiscountCart = \Cart::session('discount')->getContent()->count();
        if ($countDiscountCart >= 3) {
            $json = ['type' => 0, 'msg' => 'Discount Product is out of limit'];
            return response()->json($json);
        }

        $item = \Cart::session('discount')->get($request->productid);
        if ($item) {
            $stock = $request->quantity + $item->quantity;
            if ($product->stock < $stock) {
                $json = ['type' => 0, 'msg' => 'Cart is out of stock'];
                return response()->json($json);
            }

            $item = \Cart::session('discount')->get($request->productid);
            $item->attributes->put('product_points', $item->attributes->product_points * $request->quantity);
            $item->attributes->put('product_weight', $item->attributes->product_weight * $request->quantity);
            \Cart::update($request->productid, $item);

            $response = \Cart::update($request->productid, array(
                'quantity' => array(
                    // 'relative' => false,
                    'value' => $request->quantity,
                ),
            ));

        } else {
            $stock = $request->quantity;
            if ($product->stock < $stock) {
                $json = ['type' => 0, 'msg' => 'Cart is out of stock'];
                return response()->json($json);
            }
            $response = \Cart::session('discount')->add([
                'id' => $product->id,
                'name' => $product->product,
                'quantity' => $request->quantity,
                'price' => $price,
                'attributes' => array(
                    'product_discount' => 1,
                    // 'product_price' => $request->quantity * $product->price,
                    'product_price' => $request->quantity * $price,
                    'product_points' => 0,
                    'product_weight' => $request->quantity * $product->weight,
                    'product_image' => ($product->image) ? $product->image : null,
                ),
                // 'conditions' => $productCondition,
            ]);

        }

        if ($response) {
            $json = ['type' => 1, 'msg' => 'Discount Product is added into cart'];
        } else {
            $json = ['type' => 0, 'msg' => 'Something went wrong'];
        }
        return response()->json($json);
    }

    public function update(Request $request)
    {
        // Get the cart item
        $product = Product::find($request->productid);
        $item = \Cart::session('normal')->get($request->productid);
        $stock = $request->quantity + $item->quantity;
        if ($product->stock < $stock) {
            $json = ['type' => 0, 'msg' => 'Cart is out of stock'];
            return response()->json($json);
        }
        $item->attributes->put('product_points', $product->points * $request->quantity);
        $item->attributes->put('product_weight', $product->weight * $request->quantity);
        \Cart::session('normal')->update($request->productid, $item);

        $response = \Cart::session('normal')->update($request->productid, array(
            'quantity' => array(
                'relative' => false,
                'value' => $request->quantity,
            ),
        ));

        if ($response) {
            $cart = [
                "point" => \Cart::session('normal')->get($request->productid)->attributes->product_points,
                "totalweight" => \Cart::session('normal')->get($request->productid)->attributes->product_weight,
                "sumprice" => \Cart::session('normal')->get($request->productid)->getPriceSum(),
            ];
            $json = ['type' => 1, 'cart' => $cart];
        } else {
            $json = ['type' => 0, 'msg' => 'Something went wrong'];
        }
        return response()->json($json);

    }

    public function delete(Request $request)
    {
        $id = $request->productid;
        $isdiscount = $request->isdiscount;
        $response = ($isdiscount) ? \Cart::session('discount')->remove($id) : \Cart::session('normal')->remove($id);
        if ($response) {
            $json = ['type' => 1, 'msg' => 'Product is removed from cart'];
        } else {
            $json = ['type' => 0, 'msg' => 'Something went wrong'];
        }
        return response()->json($json);
    }

    public function ajaxList()
    {

        // $array1 = \Cart::session('normal')->getContent();
        // dd($array1);
        // $array2[] = \Cart::session('discount')->getContent();
        // $object['list'] = array_merge($array1, $array2);
        $object['list_normal'] = \Cart::session('normal')->getContent();
        $object['list_discount'] = \Cart::session('discount')->getContent();
        $object['count'] = \Cart::session('normal')->getContent()->count()+\Cart::session('discount')->getContent()->count();
        $object['subtotal'] = \Cart::session('normal')->getSubTotal()+\Cart::session('discount')->getSubTotal();
        return response()->json($object);
    }

    public function discount($val)
    {
        if ($val && SettingHelper::getSettingValueBySLug('coupon_discount') > 0) {
            Session::put('coupon_discount', SettingHelper::getSettingValueBySLug('coupon_discount'));
            $response = ['type' => 0, 'msg' => "Coupon discount is applied"];
        } else {
            Session::forget('coupon_discount');
            $response = ['type' => 0, 'msg' => "Coupon discount is removed"];
        }
        return response()->json($response);
    }
}
