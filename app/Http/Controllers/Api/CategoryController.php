<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\CategoryResource;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    // method GET
    public function index()
    {
        $categories = Category::get();
        if ($categories->count() > 0) {
            return response()->json([
                'message' => 'Get Category success',
                'data' => CategoryResource::collection($categories)
            ], 200);
        } else {
            return response()->json(['message' => 'No Record Available'], 200);
        }
    }

    // method GET categories with limited quantity by $limit parameter
    public function getLimitedCategories($limit)
    {
        $categories = Category::limit($limit)->get();

        return response()->json([
            'message' => "Get {$limit} limited categories successfully",
            'data' => CategoryResource::collection($categories)
        ], 200);
    }

    // method POST
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'category_name' => 'required|string|max:255',
            'image_category' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'required|string',
        ]);

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

        // Handle image with Storage
        if ($request->hasFile('image_category')) {
            $image = $request->file('image_category');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('categories', $imageName, 'public');
            $imageUrl = Storage::url($imagePath);
        }

        $categories = Category::create([
            'category_name' => $request->category_name,
            'image_category' => $imageUrl ?? null,
            'description' => $request->description,
        ]);

        return response()->json([
            'message' => 'Category created success',
            'data' => new CategoryResource($categories)
        ], 201);
    }

    // method GET Detail with category_id
    public function show($category_id)
    {
        try {
            $category = Category::where('category_id', $category_id)->first();
            if (!$category) {
                return response()->json([
                    'message' => 'Category not found',
                    'category_id' => $category_id
                ], 404);
            }

            return response()->json([
                'message' => 'Get category success with category_id',
                'data' => new CategoryResource($category)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get category information', [
                'error' => $e->getMessage(),
                'category_id' => $category_id
            ]);

            return response()->json([
                'message' => 'Failed to get category information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // method PUT
    public function update(Request $request, Category $category)
    {

        // Debug: Log incoming request data
        Log::info('Category Update Request', [
            'category_id' => $category->category_id,
            'method' => $request->method(),
            'request_data' => $request->all(),
            'has_file' => $request->hasFile('image_category'),
            'file_info' => $request->hasFile('image_category') ? [
                'name' => $request->file('image_category')->getClientOriginalName(),
                'size' => $request->file('image_category')->getSize(),
                'mime' => $request->file('image_category')->getMimeType(),
            ] : null,
            'has_category_name' => $request->has('category_name'),
            'has_description' => $request->has('description'),
            'category_name_value' => $request->category_name,
            'description_value' => $request->description,
        ]);

        $validator = Validator::make($request->all(), [
            'category_name' => 'sometimes|string|max:255',
            'image_category' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'sometimes|string|max:255',
        ]);

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

        // Initialize imageUrl variable to avoid undefined variable error
        $imageUrl = null;

        // Handle image with Storage
        if ($request->hasFile('image_category')) {
            $image = $request->file('image_category');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('categories', $imageName, 'public');
            $imageUrl = Storage::url($imagePath);

            // Delete old image if exists
            if ($category->image_category) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $category->image_category));
            }
        }

        // Prepare update data - only update fields that are provided
        $updateData = [];
        
        if ($request->has('category_name')) {
            $updateData['category_name'] = $request->category_name;
        }
        
        if ($request->has('description')) {
            $updateData['description'] = $request->description;
        }
        
        if ($imageUrl !== null) {
            $updateData['image_category'] = $imageUrl;
        }

        // Debug: Log what we're about to update
        Log::info('Category Update Data', [
            'category_id' => $category->category_id,
            'updateData' => $updateData,
            'updateData_empty' => empty($updateData)
        ]);

        // Perform the update only if there's data to update
        if (!empty($updateData)) {
            Log::info('Performing category update', ['data' => $updateData]);
            $result = $category->update($updateData);
            Log::info('Update result', ['result' => $result]);
            
            // Force refresh the model to get updated timestamps
            $category->refresh();
            Log::info('After refresh', [
                'updated_at' => $category->updated_at,
                'category_name' => $category->category_name,
                'description' => $category->description
            ]);
        } else {
            Log::warning('No update data provided - skipping update');
        }

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => new CategoryResource($category)
        ], 200);
    }


    // method DELETE
    public function destroy(Category $category)
    {
        if ($category->image_category) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $category->image_category));
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted success',
        ], 200);
    }
}
