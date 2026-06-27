<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserListRequest;
use App\Http\Requests\UpdateUserListRequest;
use App\Http\Resources\UserListResource;
use App\Models\UserList;
use Illuminate\Http\JsonResponse;

class UserListController extends Controller
{
    /**
     * Create (or fetch) the list for this session_token + specialty.
     */
    public function store(StoreUserListRequest $request): JsonResponse
    {
        $data = $request->validated();

        $list = UserList::firstOrCreate(
            [
                'session_token' => $data['session_token'],
                'specialty_id' => $data['specialty_id'],
            ]
        );

        $list->load('specialty');

        return (new UserListResource($list))
            ->response()
            ->setStatusCode($list->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Update the home address / coordinates of a list.
     */
    public function update(UpdateUserListRequest $request, UserList $userList): UserListResource
    {
        $userList->fill($request->validated());
        $userList->save();
        $userList->load('specialty');

        return new UserListResource($userList);
    }
}
