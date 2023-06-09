<?php

namespace App\Http\Controllers;

use App\Models\Record;
use App\Services\ChatGPTServiceInterface;
use Illuminate\Http\Request;

class ChatGPTController extends Controller
{
    public $chat_gpt_service;

    /**
     * ChatGPTサービスクラスのコンストラクタ
     *
     * @param ChatGPTService $chat_gpt_service ChatGPTサービスクラス
     */
    public function __construct(ChatGPTServiceInterface $chatGPTServiceInterface)
    {
        $this->chat_gpt_service = $chatGPTServiceInterface;
    }

    /**
     * ChatGPTから学習記録に対するアドバイス
     *
     * @param Record $record 学習記録
     * @return redirect 学習記録の詳細ページ
     */
    public function getReview(Record $record)
    {
        $generated_text = $this->chat_gpt_service->handle($record);
        $this->chat_gpt_service->saveGeneratedText($record, $generated_text);
        return redirect()->route('records.show', ['record' => $record]);
    }
}
