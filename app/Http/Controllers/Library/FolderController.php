<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\StoreFolderRequest;
use App\Http\Requests\Library\UpdateFolderRequest;
use App\Models\LibraryFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class FolderController extends Controller
{
    public function store(StoreFolderRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            $this->ensureOwnedByUser($user->id, (int) $parentId);
        }

        $nextPosition = LibraryFolder::query()
            ->whereBelongsTo($user)
            ->where('parent_id', $parentId)
            ->max('position');

        LibraryFolder::query()->create([
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'name' => $data['name'],
            'position' => ((int) $nextPosition) + 1,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Folder created.'),
        ]);

        return back();
    }

    public function update(UpdateFolderRequest $request, LibraryFolder $folder): RedirectResponse
    {
        $user = $request->user();
        $this->ensureOwnedFolder($user->id, $folder);

        $data = $request->validated();
        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            $parent = $this->ensureOwnedByUser($user->id, (int) $parentId);

            if ($parent->id === $folder->id) {
                throw ValidationException::withMessages([
                    'parent_id' => __('A folder cannot be its own parent.'),
                ]);
            }
        }

        $folder->update([
            'name' => $data['name'],
            'parent_id' => $parentId,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Folder updated.'),
        ]);

        return back();
    }

    public function destroy(LibraryFolder $folder): RedirectResponse
    {
        $user = request()->user();
        $this->ensureOwnedFolder($user->id, $folder);

        $folder->playlists()->update(['folder_id' => null]);
        $folder->children()->update(['parent_id' => null]);
        $folder->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Folder deleted.'),
        ]);

        return back();
    }

    private function ensureOwnedByUser(int $userId, int $folderId): LibraryFolder
    {
        $folder = LibraryFolder::query()->findOrFail($folderId);
        $this->ensureOwnedFolder($userId, $folder);

        return $folder;
    }

    private function ensureOwnedFolder(int $userId, LibraryFolder $folder): void
    {
        if ($folder->user_id === $userId) {
            return;
        }

        abort(403);
    }
}
