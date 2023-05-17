<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\Record;
use App\Models\ChatGPT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecordService
{
  /**
   * 合計時間を分から時間に変換する
   *
   * @param Record $duration 合計時間(HH:MM)
   * @return $hours 時間
   */
  public function convertTotalMinutesToHours($duration)
  {
    $hours = floor($duration / 60);
    return $hours;
  }

  /**
   * 合計時間を分から時間に変換する
   *
   * @param Record $duration 合計時間(HH:MM)
   * @return $minutes 分
   */
  public function convertTotalMinutesToMinutes($duration)
  {
    $minutes = $duration % 60;
    return $minutes;
  }

  /**
   * HH:MMから合計時間に変換する
   *
   * @param Record $duration 合計時間(HH:MM)
   * @return $total_minute 合計時間
   */
  public function convertHHMMToTotalMinute($duration)
  {
    $duration_arr = explode(':', $duration); // ":"で分割し、時間と分を配列で取得する
    $hours = $duration_arr[0]; // 時間を取得する
    $minutes = $duration_arr[1]; // 分を取得する
    $total_minute = ($hours * 60) + $minutes; // 時間を60で乗じて分に変換し、分と合算する
    return $total_minute;
  }

  /**
   * 学習記録に紐づくChatGPTのレビューを取得
   *
   * @param Record $record 学習記録
   * @return $generated_text レビュー
   */
  public function getGeneratedText(Record $record)
  {
    $chat_gpt_record = ChatGPT::where('record_id', $record->id)->first();
    if ($chat_gpt_record) {
      $generated_text = $chat_gpt_record->content;
      return $generated_text;
    } else {
      return null;
    }
  }

  /**
   * カンマを区切って配列に変換
   *
   * @param $tag_name タグ
   * @return $tag_array タグ(配列)
   */
  public function stringToArray($tag_name)
  {
    $tag_array = explode(',', $tag_name);
    return $tag_array;
  }

  /**
   * タグの作成または取得、学習記録との関連付けを行う
   *
   * @param Request $request リクエスト
   * @param Record $record 学習記録
   * @return void
   */
  public function processTags(Request $request, Record $record)
  {
    $tag_name = $request->input('tagName');
    $tag_array = $this->stringToArray($tag_name);

    foreach ($tag_array as $name) {
      $tag = Tag::firstOrCreate(['name' => $name]);
      $record->tags()->attach($tag->id);
    }
  }

  /**
   * 学習記録のタグを生成
   *
   * @param Request $request リクエスト
   * @param Record $record 学習記録
   * @return void
   */
  public function createTags(Request $request, Record $record)
  {
    $this->processTags($request, $record);
  }

  /**
   * 学習記録のタグを更新
   *
   * @param Request $request リクエスト
   * @param Record $record 学習記録
   * @return void
   */
  public function updateTags(Request $request, Record $record)
  {
    $this->destroyTags($request, $record);
    $this->processTags($request, $record);
  }

  /**
   * 学習記録とタグのリレーションを解除
   *
   * @param Request $request リクエスト
   * @param Record $record 学習記録
   * @return void
   */
  public function destroyTags(Request $request, Record $record)
  {
    $record->tags()->detach($request->input('tagId'));
  }

  /**
   * 学習記録一覧を取得
   *
   * @return $records 学習記録一覧
   */
  public function index()
  {
    $records = Record::NotDraft()->orderBy('created_at', 'desc')->paginate(8);
    return $records;
  }

  /**
   * 学習記録を作成
   *
   * @param Request $request リクエスト
   * @param $total_minute 合計時間
   * @return void
   */
  public function store(Request $request, $total_minute)
  {
    $record = Record::create([
      'user_id' => Auth::id(),
      'title' => $request->input('title'),
      'body' => $request->input('body'),
      'is_draft' => $request->has('is_draft'),
      'learning_date' => $request->input('learning_date'),
      'duration' => $total_minute,
    ]);

    $tag_name = $request->input('tagName');
    if ($tag_name) {
      $this->createTags($request, $record);
    }
  }

  /**
   * 学習記録を更新
   *
   * @param Request $request リクエスト
   * @param Record $record 学習記録
   * @return void
   */
  public function update(Request $request, Record $record)
  {
    $duration = $request->input('duration');
    $total_minute = $this->convertHHMMToTotalMinute($duration);
    $record->fill(array_merge($request->except('is_draft'), ['duration' => $total_minute]))->save();

    $tag_id = $request->input('tagId');
    $tag_name = $request->input('tagName');
    if ($tag_id) { // タグが存在する場合
      if (is_null($tag_name)) {
        $this->destroyTags($request, $record);
        return;
      }
      $this->updateTags($request, $record);
    } elseif ($tag_name) { // タグが存在しない場合
      $this->createTags($request, $record);
    }

    if ($request->has('is_draft')) { // 下書き保存の場合
      return;
    }
    $record->is_draft = false; // 更新の場合
    $record->save();
  }
}
