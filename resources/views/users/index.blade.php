@extends('layouts.app')
@section('title', 'Users')
@section('heading', 'Users')
@section('content')
    <div class="mb-4 flex justify-end"><a class="btn btn-primary" href="{{ route('users.create') }}">New user</a></div>
    <div class="card overflow-x-auto p-0">
        <table class="tbl">
            <thead><tr><th>Name</th><th>Email</th><th>Company</th><th>Role</th><th>Active</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr>
                    <td class="font-semibold">{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ $u->company ?? '—' }}</td>
                    <td>{{ $u->role->label() }}</td><td>{{ $u->is_active ? 'Yes' : 'No' }}</td>
                    <td class="text-right"><a class="btn btn-outline btn-sm" href="{{ route('users.edit', $u) }}">Edit</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
@endsection
