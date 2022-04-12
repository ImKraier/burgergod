<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignOrderRequest;
use App\Http\Requests\createExtraRequest;
use App\Http\Requests\markAsPickedUpRequest;
use App\Models\Categories;
use Hamcrest\Core\Set;
use Illuminate\Http\Request;
use App\Http\Requests\AdminLoginRequest as LoginRequest;
use App\Http\Requests\createCategoryRequest;
use App\Http\Requests\createItemRequest;
use App\Http\Requests\editItemRequest;
use App\Http\Requests\SetOrderStatusRequest;
use App\Http\Requests\AddDeliveryBoyRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\Items;
use App\Models\Orders;
use App\Models\Extras;
use App\Models\User;
use Hash;
use DateTime;
use DateTimeZone;


class AdminController extends Controller
{
    public function viewLogin() {
        return view('admin.login');
    }

    public function login(LoginRequest $request) {
        if ($request->validated()) {
            $credentials = $request->only('email', 'password');
            $credentials['type'] = 1 || 2;
            if (Auth::guard('admin')->attempt($credentials)) {
                toastr()->success('Te-ai autentificat cu success');
            } else {
                toastr()->error('Ceva nu a mers bine, va rugam sa incercat mai tarziu');
            }
        }
        return redirect()->back();
    }

    public function viewAdminIndex() {
        $orders = Orders::orderBy('id', 'DESC')->get();
        $deliveryBoys = User::where('type', 1)->get();
        return view('admin.dashboard', compact(['orders', 'deliveryBoys']));
    }

    public function viewItems() {
        $items = Items::get();
        $categories = Categories::get();
        return view('admin.items', compact(['items', 'categories']));
    }

    public function viewCategories() {#
        $categories = Categories::get();
        return view('admin.categories', compact('categories'));
    }

    public function createCategory(createCategoryRequest $request) {
        if($request->validated()) {
            $create = Categories::insert(['name' => $request->name]);
            if($create) {
                toastr()->success('Ai creat cu succes o noua categorie');
                return redirect()->back();
            }
        }
        return redirect()->back();
    }

    public function createItem(createItemRequest $request) {
        if($request->validated()) {
            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('items'), $imageName);
            $create = Items::insert([
                'name' => $request->name,
                'category' => $request->category,
                'description' => $request->description,
                'price' => $request->price,
                'image' => $imageName
            ]);
            if($create) {
                toastr()->success('Ai creat cu succes un nou produs');
                return redirect()->back();
            }
        }
        return redirect()->back();
    }

    public function deleteItem($itemId) {
        $itemName = Items::where('id', $itemId)->get();
        $item = Items::where('id', $itemId)->delete();
        if($item) {
            toastr()->success("Ai sters cu succes produsul {$itemName->name}");
            return redirect()->back();
        }
    }

    public function editItem($itemId) {
        $item = Items::where('id', $itemId)->get();
        $categories = Categories::get();
        if(count($item) > 0) {
            return view('admin.editItem', compact(['item', 'categories']));
        } else {
            abort(404);
        }
    }

    public function editItemValidation($itemId, editItemRequest $request) {
        if($request->validated()) {
            if (!$request->has('image')) {
                $item = Items::where('id', $itemId)->update([
                    'name' => $request->name,
                    'category' => $request->category,
                    'description' => $request->description,
                    'price' => $request->price
                ]);
                if ($item) {
                    toastr()->success("Ai editat cu succes produsul");
                    return redirect()->route('app.admin.items');
                }
            } else {
                $imageName = time() . '.' . $request->image->extension();
                $request->image->move(public_path('items'), $imageName);
                $item = Items::where('id', $itemId)->update([
                    'name' => $request->name,
                    'category' => $request->category,
                    'description' => $request->description,
                    'price' => $request->price,
                    'image' => $imageName
                ]);
                if ($item) {
                    toastr()->success("Ai editat cu succes produsul");
                    return redirect()->route('app.admin.items');
                }
            }
        }
        return redirect()->back();
    }

    public function deleteCategory($categoryId) {
        $category = Categories::where('id', $categoryId)->delete();
        if($category) {
            toastr()->success("Ai sters cu succes categoria");
            return redirect()->route('app.admin.categories');
        }
    }

    public function editCategory($categoryId) {
        $category = Categories::where('id', $categoryId)->get();
        if(count($category) > 0) {
            return view('admin.editCategory', compact('category'));
        } else {
            abort(404);
        }
    }

    public function editCategoryValidation($categoryId, createCategoryRequest $request) {
        if($request->validated()) {
            $category = Categories::where('id', $categoryId)->update([
                'name' => $request->name,
            ]);
            if($category) {
                toastr()->success("Ai editat cu succes categoria");
                return redirect()->route('app.admin.categories');
            }
        }
        return redirect()->back();
    }

    public function viewExtras() {
        $products = Items::get();
        $extras = Extras::get();
        return view('admin.extras', compact(['extras', 'products']));
    }

    public function createExtra(createExtraRequest $request) {
        if($request->validated()) {
            $create = Extras::insert([
                'name' => $request->name,
                'product' => $request->product,
                'type' => $request->value,
                'price' => $request->price
            ]);
            if($create) {
                toastr()->success("Ai creat cu succes un extra");
                return redirect()->route('app.admin.extras');
            }
        }
        return redirect()->back();
    }

    public function logout() {
        Auth::guard('admin')->logout();
        return redirect()->route('login');
    }

    public function orderPost($id, $type) {
        $date = new DateTime("now", new DateTimeZone('Europe/Bucharest') );
        $order = Orders::where('id', $id)->first();
        switch($type) {
            case 2:
                $order->status = $type;
                $order->preparing_date = $date->format('Y-m-d H:i:s');
                break;
            case 3:
                $order->status = $type;
                $order->dispatching_date = $date->format('Y-m-d H:i:s');
                break;
            case 4:
                $order->status = $type;
                $order->delivered_date = $date->format('Y-m-d H:i:s');
                break;
        }
        $order->save();
        event(new \App\Events\OrderDetails($id, $type, $date->format('Y-m-d H:i:s')));
        return redirect()->back();
    }

    public function assignOrder(AssignOrderRequest $request) {
        if($request->validated()) {
            $date = new DateTime("now", new DateTimeZone('Europe/Bucharest') );
            $order = Orders::where('id', $request->id_order)->firstOrFail();
            $order->assigned_to = $request->delivery_boy;
            $order->status = 3;
            $order->dispatching_date = $date->format('Y-m-d H:i:s');
            $order->save();
            $delivery_boy = User::where('id', $request->delivery_boy)->firstOrFail();
            $delivery_boy->delivery_presence = 2;
            $delivery_boy->save();
            event(new \App\Events\OrderDetails($request->id_order, 3, $date->format('Y-m-d H:i:s')));
            return redirect()->route('app.admin.dashboard');
        }
        return redirect()->back();
    }

    public function markAsReadyPickUp(SetOrderStatusRequest $request) {
        if($request->validated()) {
            $date = new DateTime("now", new DateTimeZone('Europe/Bucharest') );
            $order = Orders::where('id', $request->id_order)->firstOrFail();
            $order->dispatching_date = $date->format('Y-m-d H:i:s');
            $order->status = 3;
            $order->save();
            event(new \App\Events\OrderDetails($request->id_order, 3, $date->format('Y-m-d H:i:s')));
            return redirect()->route('app.admin.dashboard');
        }
        return redirect()->back();
    }

    public function markAsPickedUp(SetOrderStatusRequest $request) {
        if($request->validated()) {
            $date = new DateTime("now", new DateTimeZone('Europe/Bucharest') );
            $order = Orders::where('id', $request->id_order)->firstOrFail();
            $order->delivered_date = $date->format('Y-m-d H:i:s');
            $order->status = 4;
            $order->save();
            event(new \App\Events\OrderDetails($request->id_order, 4, $date->format('Y-m-d H:i:s')));
            return redirect()->route('app.admin.dashboard');
        }
        return redirect()->back();
    }

    public function printOrder($id) {
        $order = Orders::where('id', $id)->firstOrFail();
        return view('admin.printOrder', compact('order'));
    }

    public function viewDeliveryBoys() {
        $boys = User::where('type', '1')->get();
        return view('admin.deliveryBoys', compact('boys'));
    }

    public function addDeliveryBoy(AddDeliveryBoyRequest $request) {
        if($request->validated()) {
            $data = $request->except('_token');
            $data['type'] = 1;
            $boy = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'],
                'password' => $data['password'],
                'type' => $data['type'],
                'car_number_plate' => $data['car_number_plate']
            ]);
            if($boy) {
                toastr()->success('Ai adaugat cu succes un nou livrator!');
                return redirect()->back();
            }
        }
        return redirect()->back();
    }
}
