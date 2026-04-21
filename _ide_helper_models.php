<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string $spotify_id
 * @property string $name
 * @property ?string $album_type
 * @property ?string $release_date
 * @property ?array<int, array<string, mixed>> $images
 * @property int $total_tracks
 * @property ?Carbon $metadata_synced_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Artist> $artists
 * @property-read int|null $artists_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Track> $tracks
 * @property-read int|null $tracks_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereAlbumType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereImages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereMetadataSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereReleaseDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereTotalTracks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereUpdatedAt($value)
 */
	class Album extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $artist_id
 * @property ?string $artist_name
 * @property array<int, string> $genres
 * @property ?array<int, array{url: string, height?: int|null, width?: int|null}> $images
 * @property ?int $popularity
 * @property ?string $uri
 * @property ?array<string, mixed> $external_urls
 * @property Carbon $fetched_at
 * @property Carbon $expires_at
 * @property ?Carbon $lastfm_genres_checked_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Album> $albums
 * @property-read int|null $albums_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserTopArtist> $topForUsers
 * @property-read int|null $top_for_users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Track> $tracks
 * @property-read int|null $tracks_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereArtistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereArtistName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereExternalUrls($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereFetchedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereGenres($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereImages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereLastfmGenresCheckedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist wherePopularity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereUri($value)
 */
	class Artist extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property ?int $parent_id
 * @property string $name
 * @property int $position
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LibraryFolder> $children
 * @property-read int|null $children_count
 * @property-read LibraryFolder|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Playlist> $playlists
 * @property-read int|null $playlists_count
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\LibraryFolderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LibraryFolder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LibraryFolder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LibraryFolder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LibraryFolder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LibraryFolder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LibraryFolder whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LibraryFolder whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LibraryFolder wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LibraryFolder whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LibraryFolder whereUserId($value)
 */
	final class LibraryFolder extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $track_id
 * @property string $artist_name
 * @property string $track_name
 * @property ?string $synced_lyrics
 * @property ?string $plain_lyrics
 * @property bool $is_synced
 * @property string $source
 * @property ?Carbon $fetched_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LyricPronunciation> $pronunciations
 * @property-read int|null $pronunciations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LyricTranslation> $translations
 * @property-read int|null $translations_count
 * @method static \Database\Factories\LyricFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lyric newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lyric newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lyric query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lyric whereArtistName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lyric whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lyric whereFetchedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lyric whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lyric whereIsSynced($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lyric wherePlainLyrics($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lyric whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lyric whereSyncedLyrics($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lyric whereTrackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lyric whereTrackName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lyric whereUpdatedAt($value)
 */
	class Lyric extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $lyric_id
 * @property string $track_id
 * @property 'queued'|'processing'|'ready'|'failed' $status
 * @property ?array<int, array{index: int, timestamp: ?string, pt_br: ?string, en: ?string}> $romanized_lines
 * @property ?string $provider
 * @property ?string $model
 * @property ?Carbon $requested_at
 * @property ?Carbon $started_at
 * @property ?Carbon $completed_at
 * @property ?string $error_message
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read User $user
 * @property-read Lyric $lyric
 * @method static \Database\Factories\LyricPronunciationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation whereLyricId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation whereRequestedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation whereRomanizedLines($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation whereTrackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricPronunciation whereUserId($value)
 */
	class LyricPronunciation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $lyric_id
 * @property string $track_id
 * @property 'queued'|'processing'|'ready'|'failed' $status
 * @property ?array<int, array{index: int, timestamp: ?string, text: string, source_lang: string, pt_br: ?string, en: ?string}> $translated_lines
 * @property ?string $provider
 * @property ?string $model
 * @property ?Carbon $requested_at
 * @property ?Carbon $started_at
 * @property ?Carbon $completed_at
 * @property ?string $error_message
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read User $user
 * @property-read Lyric $lyric
 * @method static \Database\Factories\LyricTranslationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation whereLyricId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation whereRequestedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation whereTrackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation whereTranslatedLines($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LyricTranslation whereUserId($value)
 */
	class LyricTranslation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property ?int $folder_id
 * @property int $position
 * @property bool $is_hidden
 * @property bool $is_liked_playlist
 * @property string $spotify_id
 * @property string $name
 * @property ?string $description
 * @property ?array<int, array<string, mixed>> $images
 * @property ?string $owner_spotify_id
 * @property ?string $owner_name
 * @property bool $is_public
 * @property bool $is_collaborative
 * @property int $tracks_total
 * @property ?string $snapshot_id
 * @property ?string $uri
 * @property ?array<string, mixed> $external_urls
 * @property ?Carbon $synced_at
 * @property ?Carbon $expires_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read \App\Models\LibraryFolder|null $folder
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PlaylistTrack> $items
 * @property-read int|null $items_count
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\PlaylistFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereExternalUrls($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereFolderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereImages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereIsCollaborative($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereIsHidden($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereIsLikedPlaylist($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereIsPublic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereOwnerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereOwnerSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereSnapshotId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereTracksTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereUri($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Playlist whereUserId($value)
 */
	final class Playlist extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $playlist_id
 * @property ?int $track_id
 * @property string $spotify_track_id
 * @property int $position
 * @property ?Carbon $added_at
 * @property ?string $added_by_spotify_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read \App\Models\Playlist $playlist
 * @property-read \App\Models\Track|null $track
 * @method static \Database\Factories\PlaylistTrackFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistTrack newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistTrack newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistTrack query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistTrack whereAddedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistTrack whereAddedBySpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistTrack whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistTrack whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistTrack wherePlaylistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistTrack wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistTrack whereSpotifyTrackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistTrack whereTrackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlaylistTrack whereUpdatedAt($value)
 */
	final class PlaylistTrack extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $time_range
 * @property array<int, array<string, mixed>>|array<string, mixed> $payload
 * @property Carbon $fetched_at
 * @property Carbon $expires_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read User $user
 * @method static \Database\Factories\SpotifyStatFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifyStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifyStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifyStat query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifyStat whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifyStat whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifyStat whereFetchedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifyStat whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifyStat wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifyStat whereTimeRange($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifyStat whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifyStat whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifyStat whereUserId($value)
 */
	class SpotifyStat extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $status
 * @property ?Carbon $started_at
 * @property ?Carbon $finished_at
 * @property ?array<string, mixed> $meta
 * @property ?string $error
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifySyncRun newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifySyncRun newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifySyncRun query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifySyncRun whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifySyncRun whereError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifySyncRun whereFinishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifySyncRun whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifySyncRun whereMeta($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifySyncRun whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifySyncRun whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifySyncRun whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifySyncRun whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpotifySyncRun whereUserId($value)
 */
	class SpotifySyncRun extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $spotify_id
 * @property ?int $album_id
 * @property string $name
 * @property int $duration_ms
 * @property bool $explicit
 * @property ?Carbon $metadata_synced_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read \App\Models\Album|null $album
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Artist> $artists
 * @property-read int|null $artists_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserRecentPlay> $recentPlays
 * @property-read int|null $recent_plays_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserTopTrack> $topForUsers
 * @property-read int|null $top_for_users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Track newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Track newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Track query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Track whereAlbumId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Track whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Track whereDurationMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Track whereExplicit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Track whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Track whereMetadataSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Track whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Track whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Track whereUpdatedAt($value)
 */
	class Track extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $track_id
 * @property string $track_name
 * @property string $artist_name
 * @property ?string $album_name
 * @property TrackInsightStatus $status
 * @property ?string $summary
 * @property ?string $summary_pt
 * @property ?string $mood
 * @property ?string $mood_pt
 * @property ?string $meaning
 * @property ?string $meaning_pt
 * @property ?string[] $themes
 * @property ?string[] $themes_pt
 * @property ?string[] $trivia
 * @property ?string[] $trivia_pt
 * @property ?string[] $similar
 * @property ?string[] $similar_pt
 * @property ?string $provider
 * @property ?string $model
 * @property ?string $error_message
 * @property ?Carbon $started_at
 * @property ?Carbon $completed_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @method static \Database\Factories\TrackInsightFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereAlbumName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereArtistName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereMeaning($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereMeaningPt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereMood($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereMoodPt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereSimilar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereSimilarPt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereSummaryPt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereThemes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereThemesPt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereTrackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereTrackName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereTrivia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereTriviaPt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TrackInsight whereUpdatedAt($value)
 */
	class TrackInsight extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $spotify_id
 * @property string $name
 * @property ?string $email
 * @property ?string $avatar
 * @property string $spotify_token
 * @property string $spotify_refresh_token
 * @property Carbon $spotify_token_expires_at
 * @property ?string $remember_token
 * @property ?Carbon $two_factor_confirmed_at
 * @property ?string $two_factor_secret
 * @property ?string $two_factor_recovery_codes
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Artist> $artists
 * @property-read int|null $artists_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LibraryFolder> $libraryFolders
 * @property-read int|null $library_folders_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LyricPronunciation> $lyricPronunciations
 * @property-read int|null $lyric_pronunciations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LyricTranslation> $lyricTranslations
 * @property-read int|null $lyric_translations_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Playlist> $playlists
 * @property-read int|null $playlists_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserRecentPlay> $recentPlays
 * @property-read int|null $recent_plays_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SpotifyStat> $spotifyStats
 * @property-read int|null $spotify_stats_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SpotifySyncRun> $syncRuns
 * @property-read int|null $sync_runs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserTopArtist> $topArtists
 * @property-read int|null $top_artists_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserTopTrack> $topTracks
 * @property-read int|null $top_tracks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Track> $tracks
 * @property-read int|null $tracks_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSpotifyRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSpotifyToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSpotifyTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $track_id
 * @property Carbon $played_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read \App\Models\Track $track
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRecentPlay newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRecentPlay newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRecentPlay query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRecentPlay whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRecentPlay whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRecentPlay wherePlayedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRecentPlay whereTrackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRecentPlay whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserRecentPlay whereUserId($value)
 */
	class UserRecentPlay extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $artist_model_id
 * @property string $time_range
 * @property int $rank
 * @property int $score
 * @property Carbon $synced_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read \App\Models\Artist $artist
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopArtist newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopArtist newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopArtist query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopArtist whereArtistModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopArtist whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopArtist whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopArtist whereRank($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopArtist whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopArtist whereSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopArtist whereTimeRange($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopArtist whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopArtist whereUserId($value)
 */
	class UserTopArtist extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $track_id
 * @property string $time_range
 * @property int $rank
 * @property int $score
 * @property Carbon $synced_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read \App\Models\Track $track
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopTrack newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopTrack newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopTrack query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopTrack whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopTrack whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopTrack whereRank($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopTrack whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopTrack whereSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopTrack whereTimeRange($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopTrack whereTrackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopTrack whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopTrack whereUserId($value)
 */
	class UserTopTrack extends \Eloquent {}
}

