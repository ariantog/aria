# CoreAria UI Style Guide & Development Patterns

This document outlines the standard UI patterns and development practices used in the CoreAria application (specifically modeled after the **User Management** module). Adhering to these standards ensures consistency across new modules.

## 1. Page Layout & Structure

All main application pages should use the `AppSidebarLayout`.

### Header Structure
Pages should have a consistent header with Breadcrumbs, Title, Description, and Actions.

```tsx
import AppLayout from '@/layouts/app-layout';

const breadcrumbs = [
    { title: 'Module Name', href: '/module' },
    { title: 'Current Page', href: '#' },
];

export default function Page() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Page Title" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {/* Header */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">Page Title</h2>
                        <p className="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Brief description of the page.</p>
                    </div>
                    {/* Actions (Buttons) */}
                    <div className="flex items-center gap-2">
                        <Link href="/create">
                            <Button>Add New</Button>
                        </Link>
                    </div>
                </div>
                {/* Content */}
            </div>
        </AppLayout>
    );
}
```

---

## 2. Forms

Forms in CoreAria use standardized components and Inertia's `useForm` hook.

### Key Components
- **Location:** `resources/js/Components/Partial/Form/`
- ** Components:** `FormInput`, `FormSelect`
- **Button:** `resources/js/Components/ui/button` (Supports `loading` prop)

### Form Pattern
1.  **State Management:** Use `useForm` from `@inertiajs/react` (do NOT use `laravel-precognition-react` unless specifically required).
2.  **Validation:** Rely on Backend Validation (422 responses). The `errors` object from `useForm` is automatically populated.

3.  **Required Fields:** Use the `required` prop on `FormInput` or `FormSelect` to automatically display a red asterisk (*) next to the label.

4.  **Loading StSate:** Pass `processing` to the Submit `Button`.

```tsx
import { useForm } from '@inertiajs/react';
import FormInput from '@/Components/Partial/Form/FormInput';
import { Button } from '@/Components/ui/button';

const { data, setData, post, processing, errors } = useForm({
    name: '',
});

const submit = (e) => {
    e.preventDefault();
    post(route('module.store'));
};

<form onSubmit={submit} className="space-y-8" noValidate>
    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <FormInput
            id="name"
            label="Full Name"
            value={data.name}
            onChange={e => setData('name', e.target.value)}
            error={errors.name} // Automatically displays red error text
            required
        />
    </div>

    <div className="flex justify-end pt-8">
        <Button type="submit" loading={processing}>
            Save Changes
        </Button>
    </div>
</form>
```

### Visual Style
- **Inputs:** Dark mode optimized (`bg-zinc-950`, `border-zinc-800`).
- **Focus:** Blue ring (`focus:ring-blue-600/20`).
- **Icons:** Use `lucide-react` icons inside inputs where appropriate.

---

## 3. Tables & Lists

Tables should be wrapped in a card-like container with consistent styling.

### Table Structure
- **Container:** `bg-white dark:bg-zinc-900 border ... rounded-xl shadow-sm`
- **Header:** `bg-zinc-50/50 dark:bg-zinc-900/50`
- **Text:** `text-sm`, `font-medium` for primary data, `text-zinc-500` for secondary.

### Status Indicators
Use Color-coded Badges or Dots for status.

**Dot Indicator (for boolean status):**
```tsx
<div className="flex items-center gap-2">
    <span className={`h-2 w-2 rounded-full ${user.active ? 'bg-emerald-500' : 'bg-red-500'}`}></span>
    <span>{user.active ? 'Active' : 'Inactive'}</span>
</div>
```

**Badges (for roles/categories):**
```tsx
<Badge variant="secondary" className="bg-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400">
    Admin
</Badge>
```

### Pagination
Always use the `Pagination` component at the bottom of the table card.

```tsx
import Pagination from '@/Components/Pagination';

// ... inside Table Card footer
<div className="border-t ... flex justify-between">
    <div>Showing ... results</div>
    <Pagination links={data.links} />
</div>
```

---

## 4. Toast Notifications

Toasts are handled globally via `sonner`. You do **not** need to manually trigger toasts for standard CRUD actions if the backend returns Flash messages.

### Backend Implementation
In your Controller:
```php
return redirect()->route('users.index')->with('success', 'User created successfully.');
// OR
return back()->with('error', 'Something went wrong.');
```

### Frontend Behavior
The `AppSidebarLayout` automatically listens for `flash.success` and `flash.error` props and displays:
- **Success:** Green Toast (Top-Right)
- **Error:** Red Toast (Top-Right)

**Manual Trigger (if needed):**
```tsx
import { toast } from 'sonner';

toast.success('Custom success message');
```

---

## 5. Dangerous Actions

For functionality that can destroy data or block access (like Banning), use a "Danger Zone" section.

### Visual Style
- **Border:** `border-red-900/30`
- **Background:** `bg-red-900/10`
- **Icon:** `AlertTriangle` (Red)

```tsx
<div className="rounded-lg border border-red-900/30 bg-red-900/10 p-6">
    <h3 className="text-red-500">Danger Zone</h3>
    {/* ... actions ... */}
</div>
```
