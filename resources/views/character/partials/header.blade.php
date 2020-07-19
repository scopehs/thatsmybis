<ul class="no-bullet no-indent">
    <li>
        <ul class="list-inline">
            <li class="list-inline-item">
                <h{{ isset($headerSize) && $headerSize ? $headerSize : '2' }} class="font-weight-bold">
                    {{ isset($titlePrefix) && $titlePrefix ? $titlePrefix : '' }}<a href="{{route('character.show', ['guildSlug' => $guild->slug, 'name' => $character->name]) }}" class="text-{{ $character->class ? strtolower($character->class) : '' }}">{{ $character->name }}</a>{{ isset($titleSuffix) && $titleSuffix ? $titleSuffix : '' }}
                </h{{ isset($headerSize) && $headerSize ? $headerSize : '2' }}>
            </li>
            @if (isset($showEdit) && $showEdit)
                <li class="list-inline-item">
                    <a href="{{ route('character.edit', ['guildSlug' => $guild->slug, 'name' => $character->name]) }}">
                        <span class="fas fa-fw fa-pencil"></span>
                        edit
                    </a>
                </li>
            @endif
        </ul>
    </li>
    @if ($character->raid || $character->class)
        <li>
            <span class="font-weight-bold">
                {{ $character->raid ? $character->raid->name : '' }}
            </span>
            {{ $character->class ? $character->class : '' }}
        </li>
    @endif

    @if (!isset($showDetails) || $showDetails)
        @if ($character->level || $character->race || $character->spec)
            <li>
                <small>
                    {{ $character->level ? $character->level : '' }}
                    {{ $character->race  ? $character->race : '' }}
                    {{ $character->spec  ? $character->spec : '' }}
                </small>
            </li>
        @endif

        @if ($character->rank || $character->profession_1 || $character->profession_2)
            <li>
                <small>
                    {{ $character->rank         ? 'Rank ' . $character->rank . ($character->profession_1 || $character->profession_2 ? ',' : '') : '' }}
                    {{ $character->profession_1 ? $character->profession_1 . ($character->profession_2 ? ',' : '') : '' }}
                    {{ $character->profession_2 ? $character->profession_2 : ''}}
                </small>
            </li>
        @endif
    @endif

    @if ((!isset($showOwner) && isset($character->member) && $character->member) || (isset($showOwner) && $showOwner && isset($character->member) && $character->member))
        <li>
            <small>
                <a href="{{route('member.show', ['guildSlug' => $guild->slug, 'username' => $character->member->username]) }}" class="">
                    {{ $character->member->username }}'s character
                </a>
            </small>
        </li>
    @endif
</ul>