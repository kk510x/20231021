<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tweet;
use App\Models\TweetFavorite;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;
use Faker\Generator;
use Illuminate\Container\Container;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class TweetController extends Controller
{
    public function index(Request $request)
    {
        $keyword = $request->input('keyword', '');
        return Inertia::render('Index', [
            'keyword' => $keyword
        ]);
    }


    public function timeline(Request $request)
    {
        $keyword = $request->input('keyword', '');
        //usleep(150000);
        $query = Tweet::with('parent', 'parent.user', 'user')
            ->with(['replies' => function ($query) {
                return $query->limit(1);
            }])
            ->where('type', '!=', 'reply');

        if (!empty($keyword)) {
            $query->where('text', 'like', '%' . $keyword . '%');
        }

        $tweets = $query->orderBy('id', 'desc')
            ->paginate(10);

        if ($request->wantsJson()) {
            return $tweets->toArray();
        } else {
            return Inertia::render('Index', [
                'tweetPagination' => $tweets
            ]);
        }
    }


    public function replies($tweet_id, Request $request)
    {
        usleep(150000);
        $tweets = Tweet::with('parent', 'parent.user', 'user')
            ->with(['replies' => function ($query) {
                return $query->limit(1);
            }])
            ->where('parent_id', $tweet_id)
            ->where('type', 'reply')
            ->orderBy('id', 'desc')
            ->paginate(10);

        return $tweets->toArray();
    }


    public function user_tweets($user_id, Request $request)
    {
        usleep(150000);
        $tweets = Tweet::with('parent', 'parent.user', 'user')
            ->with(['replies' => function ($query) {
                return $query->limit(1);
            }])
            ->where('user_id', $user_id)
            ->where('type', '!=', 'reply')
            ->orderBy('id', 'desc')
            ->paginate(10);


        if ($request->wantsJson()) {
            return $tweets->toArray();
        } else {
            return Inertia::render('Index', [
                'tweetPagination' => $tweets
            ]);
        }
    }


    public function show($tweet_id): Response
    {
        $tweet = Tweet::with('parent', 'parent.user', 'user')
            ->with(['replies' => function ($query) {
                return $query->limit(10);
            }])
            ->where('id', $tweet_id)->first();

        $tweet->increment_counter('count_views');

        return Inertia::render('Show', [
            'tweet' => $tweet
        ]);
    }


    public function store(Request $request)
    {
        $valid = $request->validate([
            'text' => 'required',
            'file' => 'max:2048'
        ]);

        $newTweet = new Tweet;
        $newTweet->user_id = Auth::id();
        $newTweet->text = $valid['text'];

        if ($valid['file'] != 'null') {
            $fileName = time() . '_' . $valid['file']->getClientOriginalName();
            $filePath = $valid['file']->store('uploads', 'public');
            $newTweet->path = $filePath;
        }

        $newTweet->save();

        $tweet = Tweet::where('id', $newTweet->id)->with('parent', 'parent.user', 'user')->first();
        return $tweet;
    }

    public function reply(Tweet $tweet, Request $request)
    {
        $newTweet = new Tweet;
        $newTweet->user_id = Auth::id();
        $newTweet->type = 'reply';
        $newTweet->parent_id = $tweet->id;

        $valid = $request->validate([
            'text' => 'required',
            'file' => 'max:2048'
        ]);

        if ($valid['file'] != 'null') {
            $fileName = time() . '_' . $valid['file']->getClientOriginalName();
            $filePath = $valid['file']->storeAs('uploads', $fileName, 'public');
            $newTweet->path = $filePath;
        }

        $newTweet->text = $valid['text'];

        $newTweet->save();

        $tweet->increment_counter('count_replies');

        $tweet = Tweet::where('id', $newTweet->id)->with('parent', 'parent.user', 'user')->first();
        return $tweet;
    }

    public function retweet(Tweet $tweet, Request $request)
    {
        $valid = $request->validate([
            'parent_id' => [
                Rule::unique('tweets')
                    ->where('parent_id', $tweet->id)
                    ->where('user_id', Auth::id())
                    ->where('type', 'retweet')
            ],
        ]);

        $newTweet = new Tweet;
        $newTweet->user_id = Auth::id();
        $newTweet->type = 'retweet';
        $newTweet->parent_id = $tweet->id;

        $newTweet->save();

        $tweet->increment_counter('count_retweets');

        $tweet = Tweet::where('id', $newTweet->id)->with('parent', 'parent.user', 'user')->first();
        return $tweet;
    }


    public function quote(Tweet $tweet, Request $request)
    {

        $newTweet = new Tweet;
        $newTweet->user_id = Auth::id();
        $newTweet->type = 'quote';
        $newTweet->parent_id = $tweet->id;


        $valid = $request->validate([
            'text' => 'required',
            'file' => 'max:2048'
        ]);

        if ($valid['file'] != 'null') {
            $fileName = time() . '_' . $valid['file']->getClientOriginalName();
            $filePath = $valid['file']->storeAs('uploads', $fileName, 'public');
            $newTweet->path = $filePath;
        }

        $newTweet->text = $valid['text'];

        $newTweet->save();

        $tweet->increment_counter('count_retweets');
        $tweet = Tweet::where('id', $newTweet->id)->with('parent', 'parent.user', 'user')->first();
        return $tweet;
    }


    public function favorite_tweet(Request $request)
    {
        $valid = $request->validate([
            'tweet_id' => [
                Rule::unique('tweet_favorites')
                    ->where('tweet_id', $request->tweet_id)
                    ->where('user_id', Auth::user()->id)
            ],
        ]);

        $tweetFavorite = new TweetFavorite;
        $tweetFavorite->tweet_id = $valid['tweet_id'];
        $tweetFavorite->user_id = Auth::user()->id;
        if ($tweetFavorite->save()) {
            $tweet = Tweet::where('id', $valid['tweet_id'])->first();
            $tweet->increment_counter('count_favorites');
        }
        $tweet = Tweet::where('id', $valid['tweet_id'])->with('parent', 'parent.user', 'user')->first();

        if ($request->wantsJson()) {
            return $tweet->toArray();
        }
    }

    public function unfavorite_tweet(Request $request)
    {
        $valid = $request->validate([
            'tweet_id' => [
                'required'
            ],
        ]);

        $tweetFavorite = TweetFavorite::where('tweet_id', $valid['tweet_id'])->where('user_id', Auth::user()->id)->first();

        if (!$tweetFavorite) {
            return response()->json([
                'message' => "The tweet is not favorited by user."
            ], 422);
        }

        $tweetFavorite->delete();
        $tweet = Tweet::where('id', $valid['tweet_id'])->first();
        $tweet->decrement_counter('count_favorites');
        $tweet = Tweet::where('id', $valid['tweet_id'])->with('parent', 'parent.user', 'user')->first();
        if ($request->wantsJson()) {
            return $tweet->toArray();
        }
    }

    public function delete_tweet($tweet_id)
    {
        if (!Auth::user()) {
            return response()->json([
                'message' => "The tweet is not posted by user."
            ], 422);
        }
        $tweet = Tweet::where('id', $tweet_id)->first();
        if ($tweet->user_id != Auth::user()->id) {
            return response()->json([
                'message' => "The tweet is not posted by user."
            ], 422);
        }
        if ($tweet->parent_id && in_array($tweet->type, ['retweet', 'reply'])) {
            $parentTweet = Tweet::query()->find($tweet->parent_id);
            $counterMap = [
                'retweet' => 'count_retweets',
                'reply' => 'count_replies',
            ];
            $parentTweet->decrement_counter($counterMap[$tweet->type]);
        }
        $tweet->delete();
        Tweet::query()->where('parent_id', $tweet_id)->delete();
        return back();
    }

    public function trends()
    {
        $generator = app()->make(Generator::class);
        $result = [];
        $count = mt_rand(5, 10);
        foreach (range(0, $count) as $i) {
            $result[] = [
                'top' => $generator->words(mt_rand(2, 5), true),
                'body' => $generator->name(),
                'count' => mt_rand(1, 9999)
            ];
        }
        return response()->json([
            'trendList' => $result
        ]);
    }
}
