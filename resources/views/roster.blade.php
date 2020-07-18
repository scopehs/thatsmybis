@extends('layouts.app')
@section('title', 'Roster - ' . config('app.name'))

@section('content')

<div class="container-fluid">
    <div class="col-12 p-4">
        <div>
            <ul class="list-inline">
                <li>
                    <span class="text-muted fas fa-fw fa-eye-slash"></span>
                    <strong>Column Visibility</strong>
                </li>
                <li class="list-inline-item">
                    <a class="toggle-column-default cursor-pointer font-weight-bold" href="">Defaults</a>
                </li>
                <li class="list-inline-item">&sdot;</li>
                <li class="list-inline-item">
                    <a class="toggle-column cursor-pointer" data-column="1" href="">
                        <span class="text-muted fas fa-fw fa-sack"></span>
                        Loot Received
                    </a>
                </li>
                <li class="list-inline-item">&sdot;</li>
                <li class="list-inline-item">
                    <a class="toggle-column cursor-pointer" data-column="2" href="">
                        <span class="text-muted fas fa-fw fa-scroll-old"></span>
                        Wishlist
                    </a>
                </li>
                <li class="list-inline-item">&sdot;</li>
                <li class="list-inline-item">
                    <a class="toggle-column cursor-pointer" data-column="3" href="">
                        <span class="text-muted fas fa-fw fa-book"></span>
                        Recipes
                    </a>
                </li>
                <li class="list-inline-item">&sdot;</li>
                <li class="list-inline-item">
                    <a class="toggle-column cursor-pointer" data-column="4" href="">
                        <span class="text-muted fab fa-fw fa-discord"></span>
                        Roles
                    </a>
                </li>
                <li class="list-inline-item">&sdot;</li>
                <li class="list-inline-item">
                    <a class="toggle-column cursor-pointer" data-column="5" href="">
                        <span class="text-muted fas fa-fw fa-comment-alt-lines"></span>
                        Notes
                    </a>
                </li>
            </ul>
        </div>
        <div class="mt-4">
            <ul class="list-inline">
                <li class=" list-inline-item">
                    <label for="raid_filter">
                        <span class="text-muted fas fa-fw fa-users-crown"></span>
                        Raid
                    </label>
                    <select id="raid_filter" class="form-control">
                        <option value="" class="bg-tag">—</option>
                        @foreach ($raids as $raid)
                            <option value="{{ $raid->name }}" class="bg-tag" style="color:{{ $raid->getColor() }};">
                                {{ $raid->name }}
                            </option>
                        @endforeach
                    </select>
                </li>
                <li class=" list-inline-item">
                    <label for="class_filter">
                        <span class="text-muted fas fa-fw fa-axe-battle"></span>
                        Class
                    </label>
                    <select id="class_filter" class="form-control">
                        <option value="" class="bg-tag">—</option>
                        @foreach (App\Character::classes() as $class)
                            <option value="{{ $class }}" class="bg-tag text-{{ strtolower($class) }}-important">
                                {{ $class }}
                            </option>
                        @endforeach
                    </select>
                </li>
            </ul>
        </div>
    </div>
    <div class="col-12 p-3 rounded">
        <table id="roster" class="col-xs-12 table table-border table-hover stripe">
        </table>
    </div>
</div>

@endsection

@section('scripts')
<script>
    var characters = {!! $characters->makeVisible('officer_note')->toJson() !!};
    var guild = {!! $guild->toJson() !!};
    var raids = {!! $raids->toJson() !!};
    {{-- TODO PERMISSIONS FOR NOTE --}}
    var showOfficerNote = true;
</script>
<script src="{{ asset('js/roster.js') }}"></script>
@endsection
