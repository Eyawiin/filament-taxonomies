<?php

use Eyawiin\FilamentTaxonomies\Concerns\HasTaxonomies;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConsumerArticle extends Model
{
    use HasTaxonomies;

    protected $table = 'consumer_articles';

    protected $guarded = [];

    public $timestamps = false;
}

class ConsumerVideo extends ConsumerArticle
{
    protected $table = 'consumer_videos';
}

class ConsumerUuid extends ConsumerArticle
{
    use HasUuids;

    protected $table = 'consumer_uuid_owners';
}

class ConsumerUlid extends ConsumerArticle
{
    use HasUlids;

    protected $table = 'consumer_ulid_owners';
}

function verifyConsumerAssignments(): void
{
    foreach (['consumer_articles', 'consumer_videos'] as $name) {
        Schema::create($name, fn (Blueprint $table) => $table->id());
    }
    foreach (['consumer_uuid_owners', 'consumer_ulid_owners'] as $name) {
        Schema::create($name, fn (Blueprint $table) => $table->string('id', 36)->primary());
    }
    $article = ConsumerArticle::create([]);
    $video = ConsumerVideo::create(['id' => $article->id]);
    $topics = Taxonomy::create(['name' => 'Assignment topics', 'slug' => 'assignment-topics']);
    $term = $topics->terms()->create(['name' => 'Selected', 'slug' => 'selected']);
    $tags = Taxonomy::create(['name' => 'Assignment tags', 'slug' => 'assignment-tags']);
    $tag = $tags->terms()->create(['name' => 'Preserved', 'slug' => 'preserved']);
    $article->attachTaxonomyTerms('assignment-topics', [$term->id]);
    $article->attachTaxonomyTerms($tags, [$tag->id]);
    $video->attachTaxonomyTerms($topics, [$term->id]);
    foreach ([ConsumerUuid::create([]), ConsumerUlid::create([])] as $stringOwner) {
        $stringOwner->attachTaxonomyTerms($topics, [$term->id]);
        requireConsumer($stringOwner->fresh()->taxonomyTerms->modelKeys() === [$term->id], 'Native generated string-owner assignment failed.');
        requireConsumer(DB::table('taxonomy_term_assignments')->where('assignable_type', $stringOwner->getMorphClass())->value('assignable_id') === $stringOwner->getRawOriginal('id'), 'Generated owner key was changed.');
    }
    $article->syncTaxonomyTerms($topics->id, []);
    requireConsumer($article->fresh()->taxonomyTerms->modelKeys() === [$tag->id], 'Consumer scoped assignment sync failed.');
    requireConsumer(ConsumerVideo::whereHas('taxonomyTerms', fn ($query) => $query->where('taxonomy_terms.id', $term->id))->count() === 1, 'Consumer relation query failed.');
    DB::transaction(fn () => $article->delete());
    requireConsumer($video->fresh()->taxonomyTerms->modelKeys() === [$term->id], 'Consumer owner isolation failed.');
    $topics->delete();
    $tags->delete();
    requireConsumer(DB::table('taxonomy_term_assignments')->count() === 0, 'Consumer assignment cascade failed.');
    echo "PASS copied consumer assignment API, scoped sync, owner isolation, queries and cleanup.\n";
}
