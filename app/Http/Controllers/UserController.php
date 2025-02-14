<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCartRequest;
use Illuminate\Http\Request;
use App\Models\Items;
use App\Models\User;
use App\Models\Store;
use App\Models\Categories;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserController extends Controller
{
    public function viewHomepage() {
        $categories = Categories::with('items')->get();
        $topItems = Items::where('category', 2)->get();
        $sessionOrders = OrderController::getSessionOrders();
        $storeOnline = Store::where('name', 'store_online')->first()->value;
        return view('homepage', compact(['topItems', 'categories', 'sessionOrders', 'storeOnline']));
    }

    public function viewItem($id) {
        $item = Items::where('id', $id)->with('extras')->firstOrFail();
        $sessionOrders = OrderController::getSessionOrders();
        return view('pages.item', compact(['item', 'sessionOrders']));
    }

    public function login(LoginRequest $request) {
        if($request->validated()) {
            $credentials = $request->only('email', 'password');
            if (Auth::attempt($credentials)) {
                if(session('cart') && (json_decode(session('cart')) != json_decode(Auth::user()->cart))) {
                    $cart = json_decode(Auth::user()->cart);
                    $sessionCart = json_decode(session('cart'));
                    $full_cart = array_merge($cart, $sessionCart);
                    Auth::user()->cart = json_encode($full_cart);
                    Auth::user()->save();
                    session(['cart' => json_encode($full_cart)]);
                } else {
                    session(['cart' => Auth::user()->cart]);
                }
                toastr()->success('Te-ai autentificat cu success');
            } else {
                toastr()->error('Ceva nu a mers bine, va rugam sa incercat din nou');
            }
        }
        return redirect()->back();
    }

    public function logout() {
        Auth::logout();
        session()->flush();
        return redirect()->route('app.home');
    }

    private function existsUser(string $email)
    {
        try {
            $user = User::where('email', $email)->select('id', 'email')->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return false;
        }

        if ($user) {
            return true;
        }
        return false;
    }

    public function register(RegisterRequest $request) {
        if($request->validated()) {
            if ($this->existsUser($request->email)) {
                toastr()->error('E-mail-ul pe care l-ați introdus este deja folosit.');
                return redirect()->route('app.home');
            }
            $create = User::create($request->except(['_token', 'password_confirmation']));
            if ($create) {
                Auth::login($create);
                toastr()->success('Te-ai înregistrat cu succes!');
                return redirect()->route('app.home');
            }
        }
        return redirect()->route('app.home');
    }

    public function addToCart(AddCartRequest $request) {
        if($request->validated()) {
            $cart = (session('cart')) ? json_decode(session('cart')) : [];
            $item = $request->item;
            $extra = $request->except(['item', 'total_price', '_token', 'quantity']);
            $extras = array();
            $extraQuantity = array();
            $price = $request->total_price;
            if(count($extra) > 0) {
                foreach($extra as $key => $extraFor) {
                    if(preg_match('/^extra_[0-9]*/', $key)) {
                        array_push($extras, $extraFor);
                    } else {
                        if($extraFor > 0)
                            array_push($extraQuantity, $extraFor);
                    }
                }
            }
            array_unshift($extras, "");
            array_unshift($extraQuantity, "");
            unset($extras[0]);
            unset($extraQuantity[0]);
            $quantity = $request->quantity;
            $new_extras = [
                'item' => $item,
                'price' => $price,
                'extra' => $extras,
                'extraQuantity' => $extraQuantity,
                'quantity' => $quantity
            ];
            array_push($cart, $new_extras);
            session(['cart' => json_encode($cart)]);
            if(Auth::check()) {
                Auth::user()->cart = json_encode($cart);
                Auth::user()->save();
            }
            return redirect()->back();
        }
        return redirect()->back();
    }

    public function viewCart() {
        $sessionOrders = OrderController::getSessionOrders();
        if(Auth::check()) {
            $items = json_decode(Auth::user()->cart);
        } else {
            $items = json_decode(session('cart'));
        }
//        return dd($items);
        return view('pages.cart', compact(['items', 'sessionOrders']));
    }

    public function removeFromCart($index) {
        $items = json_decode(session('cart'));
        array_splice($items, $index, 1);
        session(['cart' => json_encode($items)]);
        if(Auth::check()) {
            Auth::user()->cart = json_encode($items);
            Auth::user()->save();
        }
        toastr()->success('Ai scos cu succes un item din cosul tau de cumparaturi');
        return redirect()->back();
    }

    public function viewMenu() {
        $categories = Categories::with('items')->get();
        $items = Items::where('visible', 1)->orderBy('id', 'DESC')->get();
        $sessionOrders = OrderController::getSessionOrders();
        return view('pages.menu', compact(['items', 'categories', 'sessionOrders']));
    }

    public function viewAboutUs() {
        $sessionOrders = OrderController::getSessionOrders();
        return view('pages.about_us', compact('sessionOrders'));
    }

    public function viewContact() {
        $sessionOrders = OrderController::getSessionOrders();
        return view('pages.contact', compact('sessionOrders'));
    }

    public function viewShop() {
        $sessionOrders = OrderController::getSessionOrders();
        return view('pages.shop_offline', compact('sessionOrders'));
    }

    public function viewTerms() {
        $sessionOrders = OrderController::getSessionOrders();
        return view('pages.terms', compact('sessionOrders'));
    }
}
