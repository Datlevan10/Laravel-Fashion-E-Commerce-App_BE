<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Admin;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\AdminResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    // method GET
    public function index()
    {
        $admins = Admin::get();
        if ($admins->count() > 0) {
            return response()->json([
                'message' => 'Get admin success',
                'data' => AdminResource::collection($admins)
            ], 200);
        } else {
            return response()->json(['message' => 'No record available'], 200);
        }
    }

    // method POST
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'user_name' => [
                'required',
                'string',
                'max:255',
                'unique:admins,user_name',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],
            'full_name' => 'required|string|max:255',
            'gender' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'email' => ['required', 'string', 'email', 'regex:/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\\.,;:\s@\"]+\.)+[^<>()[\]\\.,;:\s@\"]{2,})$/i', 'unique:admins,email'],
            'phone_number' => ['required', 'string', 'regex:/^[0-9]{10}$/', 'unique:admins,phone_number'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            ],
            // 'password' => 'required|string|min:8',
            'address' => 'nullable|string|max:255',
        ]);

        $date_of_birth = null;
        if ($request->date_of_birth) {
            if (Carbon::hasFormat($request->date_of_birth, 'd/m/Y')) {
                $date_of_birth = Carbon::createFromFormat('d/m/Y', $request->date_of_birth)->format('Y-m-d');
            } elseif (Carbon::hasFormat($request->date_of_birth, 'd-m-Y')) {
                $date_of_birth = Carbon::createFromFormat('d-m-Y', $request->date_of_birth)->format('Y-m-d');
            } elseif (Carbon::hasFormat($request->date_of_birth, 'Y/m/d')) {
                $date_of_birth = Carbon::createFromFormat('Y/m/d', $request->date_of_birth)->format('Y-m-d');
            } elseif (Carbon::hasFormat($request->date_of_birth, 'Y-m-d')) {
                $date_of_birth = Carbon::createFromFormat('Y-m-d', $request->date_of_birth)->format('Y-m-d');
            } else {
                return response()->json([
                    'message' => 'Invalid date format. Accepted formats are d/m/Y, d-m-Y, Y/m/d, Y-m-d.',
                ], 422);
            }
        }

        if ($validator->fails()) {
            Log::error('Validation failed', [
                'errors' => $validator->messages(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Field is empty or invalid',
                'error' => $validator->messages(),
            ], 422);
        }

        $hashed_password = Hash::make($request->password);

        // Handle image with Storage
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('admins', $imageName, 'public');
            $imageUrl = Storage::url($imagePath);
        }

        $admins = Admin::create([
            'user_name' => $request->user_name,
            'full_name' => $request->full_name,
            'gender' => $request->gender,
            'date_of_birth' => $date_of_birth,
            'image' => $imageUrl ?? null,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => $hashed_password,
            'address' => $request->address,
        ]);

        return response()->json([
            'message' => 'Admin created success',
            'data' => new AdminResource($admins)
        ], 201);
    }

    // method GET Detail
    public function show($admin_id)
    {
        try {
            $admin = Admin::where('admin_id', $admin_id)->first();
            if (!$admin) {
                return response()->json([
                    'message' => 'Admin not found',
                    'admin_id' => $admin_id
                ], 404);
            }

            return response()->json([
                'message' => 'Get admin success with admin_id',
                'data' => new AdminResource($admin)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get admin information', [
                'error' => $e->getMessage(),
                'admin_id' => $admin_id
            ]);

            return response()->json([
                'message' => 'Failed to get admin information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // method PUT
    public function update(Request $request, Admin $admin)
    {

        $validator = Validator::make($request->all(), [
            'user_name' => [
                'sometimes',
                'string',
                'max:255',
                'unique:admins,user_name',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],
            'full_name' => 'sometimes|string|max:255',
            'gender' => 'sometimes|string|max:255',
            'date_of_birth' => 'sometimes|date',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'email' => ['sometimes', 'string', 'email', 'regex:/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\\.,;:\s@\"]+\.)+[^<>()[\]\\.,;:\s@\"]{2,})$/i', 'unique:admins,email'],
            'phone_number' => ['sometimes', 'string', 'regex:/^[0-9]{10}$/', 'unique:admins,phone_number'],
            'password' => [
                'sometimes',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            ],
            // 'password' => 'sometimes|string|min:8',
            'address' => 'sometimes|string|max:255',
        ]);

        $date_of_birth = null;
        if ($request->date_of_birth) {
            $formats = ['d/m/Y', 'd-m-Y', 'Y/m/d', 'Y-m-d'];
            foreach ($formats as $format) {
                if (Carbon::hasFormat($request->date_of_birth, $format)) {
                    $date_of_birth = Carbon::createFromFormat($format, $request->date_of_birth)->format('Y-m-d');
                    break;
                }
            }
        }

        if ($validator->fails()) {
            Log::error('Validation failed', [
                'errors' => $validator->messages(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Field is empty or invalid',
                'error' => $validator->messages(),
            ], 422);
        }

        $hashed_password = Hash::make($request->password);

        // Handle image with Storage
        if ($request->hasFile('image')) {
            if ($admin->image) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $admin->image));
            }
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('admins', $imageName, 'public');
            $imageUrl = Storage::url($imagePath);
        }

        $admin->update([
            'user_name' => $request->user_name ?? $admin->user_name,
            'full_name' => $request->full_name ?? $admin->full_name,
            'gender' => $request->gender ?? $admin->gender,
            'date_of_birth' => $date_of_birth ?? $admin->date_of_birth,
            'image' => $imageUrl ?? $admin->image,
            'email' => $request->email ?? $admin->email,
            'phone_number' => $request->phone_number ?? $admin->phone_number,
            'password' => $hashed_password ?? $admin->password,
            'address' => $request->address ?? $admin->address,
        ]);

        return response()->json([
            'message' => 'Admin updated success',
            'data' => new AdminResource($admin)
        ], 200);
    }

    // method DELETE
    public function destroy(Admin $admin)
    {
        if ($admin->image) {
            $imagePaths = json_decode($admin->image);
            foreach ($imagePaths as $imagePath) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $imagePath));
            }
        }

        $admin->delete();

        return response()->json([
            'message' => 'Admin deleted successfully',
        ], 200);
    }

    // Admin authentication methods
    public function authenticateLoginAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string',
            'password' => 'required|string',
        ], [
            'identifier.required' => 'The identifier field is required.',
            'password.required' => 'The password field is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $admin = Admin::where('email', $request->identifier)
            ->orWhere('phone_number', $request->identifier)
            ->orWhere('user_name', $request->identifier)
            ->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'message' => 'Incorrect username and password',
                'error_code' => 'AUTH_FAILED',
            ], 401);
        }

        if ($admin->is_active === false) {
            return response()->json([
                'message' => 'Admin account is deactivated',
                'error_code' => 'ACCOUNT_DEACTIVATED',
            ], 401);
        }

        $admin->last_login = now();
        $admin->save();

        try {
            $accessToken = $admin->createToken('admin-access-token', ['*'], now()->addMinutes(120))->plainTextToken;
        } catch (\Exception $e) {
            \Log::error('Admin login token creation failed', [
                'admin_id' => $admin->admin_id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Authentication failed',
                'error' => 'Token creation failed'
            ], 500);
        }

        $refreshToken = Str::random(64);
        $expiresAt = now()->addDays(30);

        DB::table('refresh_tokens')
            ->where('admin_id', $admin->admin_id)
            ->delete();

        DB::table('refresh_tokens')->insert([
            'admin_id' => $admin->admin_id,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'id' => $admin->admin_id,
                'user' => new AdminResource($admin),
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => 7200, // 120 minutes = 7200 seconds
                'role' => $admin->role,
                'permissions' => json_decode($admin->permissions) ?? []
            ],
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        DB::table('refresh_tokens')
            ->where('admin_id', $request->user()->admin_id)
            ->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ], 200);
    }

    public function refreshAccessToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $refreshToken = $request->refresh_token;

        $tokenRecord = DB::table('refresh_tokens')
            ->where('refresh_token', $refreshToken)
            ->first();

        if (!$tokenRecord) {
            return response()->json([
                'message' => 'Invalid refresh token.',
            ], 401);
        }

        if (Carbon::now()->greaterThan(Carbon::parse($tokenRecord->expires_at))) {
            DB::table('refresh_tokens')->where('id', $tokenRecord->id)->delete();
            return response()->json([
                'message' => 'Refresh token has expired.',
            ], 401);
        }

        $admin = Admin::find($tokenRecord->admin_id);
        if (!$admin || $admin->is_active === false) {
            return response()->json([
                'message' => 'Admin not found or deactivated.',
            ], 404);
        }

        DB::table('refresh_tokens')->where('id', $tokenRecord->id)->delete();

        $newAccessToken = $admin->createToken('admin-access-token', ['*'], now()->addMinutes(120))->plainTextToken;
        $newRefreshToken = Str::random(64);

        DB::table('refresh_tokens')->insert([
            'admin_id' => $admin->admin_id,
            'refresh_token' => $newRefreshToken,
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Access token refreshed successfully.',
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => 7200,
        ], 200);
    }
}
