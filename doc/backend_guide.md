# CoreAria Backend Technical Guide: Permissions

This document outlines the standard pattern for implementing permissions in CoreAria, modeled after the **Post** module.

This pattern centralizes permission names in the Model and enforces them via `Gate` in the Controller.

## 1. Defining Permissions in the Model

Define a static `getPermissions()` method in your Model to return an array mapping actions to permission strings. This avoids hardcoding strings across your application.

**Example: `App\Models\Post.php`**

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    /**
     * Define permissions associated with this model.
     * 
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view'   => 'posts-list',
            'create' => 'posts-create',
            'edit'   => 'posts-edit',
            'delete' => 'posts-delete',
        ];
    }
}
```

## 2. Enforcing Permissions in the Controller

Use `Illuminate\Support\Facades\Gate` to authorize actions in your Controller methods. Retrieve the specific permission string dynamically from the Model.

**Example: `App\Http\Controllers\PostController.php`**

```php
namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    public function index()
    {
        // Optional: specific check for viewing the list
        // Gate::authorize(Post::getPermissions()['view']);

        return Inertia::render('Posts/Index', [
            'posts' => Post::with('user')->latest()->get(),
            // Pass specific "can" checks to the frontend for UI logic
            'can' => [
                'create_post' => auth()->user()->can(Post::getPermissions()['create']),
            ]
        ]);
    }

    public function create()
    {
        // Enforce 'create' permission
        Gate::authorize(Post::getPermissions()['create']);

        return Inertia::render('Posts/Create');
    }

    public function store(Request $request)
    {
        // Enforce 'create' permission
        Gate::authorize(Post::getPermissions()['create']);

        // ... store logic ...
    }

    public function edit(Post $post)
    {
        // Enforce 'edit' permission
        Gate::authorize(Post::getPermissions()['edit']);

        return Inertia::render('Posts/Edit', [
            'post' => $post
        ]);
    }

    public function update(Request $request, Post $post)
    {
        // Enforce 'edit' permission
        Gate::authorize(Post::getPermissions()['edit']);

        // ... update logic ...
    }

    public function destroy(Post $post)
    {
        // Enforce 'delete' permission
        Gate::authorize(Post::getPermissions()['delete']);

        $post->delete();
        
        return redirect()->route('posts.index')->with('success', 'Post deleted successfully.');
    }
}
```

## 3. Why this Pattern?
1.  **Centralized Definition:** Permission strings (`posts-create`) are defined in one place (the Model). If the string changes, you update it once.
2.  **Explicit Authorization:** `Gate::authorize()` clearly shows which permission is required for each action.
3.  **Type Safety:** Using the array key (e.g., `['create']`) reduces the risk of typos compared to repeating string literals.
