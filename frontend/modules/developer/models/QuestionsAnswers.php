<?php

namespace frontend\modules\developer\models;

use common\models\Activity;
use common\models\MarkdownParse;
use kartik\markdown\Markdown;
use Yii;
use common\models\User;
use yii\helpers\HtmlPurifier;

/**
 * This is the model class for table "questions_answers".
 *
 * @property int $id
 * @property int $user_id Пользователь
 * @property int $question_id Вопрос
 * @property string $text Текст
 * @property int $rating Рейтинг
 * @property int $selected Выбран как правильный
 * @property string $date Дата
 *
 * @property Questions $question
 * @property User $user
 * @property QuestionsAnswersRating[] $questionsAnswersRatings
 */
class QuestionsAnswers extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'questions_answers';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'question_id', 'text'], 'required'],
            [['user_id', 'question_id', 'rating', 'selected'], 'integer'],
            [['text'], 'string'],
            [['date'], 'safe'],
            [['question_id'], 'exist', 'skipOnError' => true, 'targetClass' => Questions::className(), 'targetAttribute' => ['question_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('questions', 'ID'),
            'user_id' => Yii::t('questions', 'Пользователь'),
            'question_id' => Yii::t('questions', 'Вопрос'),
            'text' => Yii::t('questions', 'Текст'),
            'rating' => Yii::t('questions', 'Рейтинг'),
            'selected' => Yii::t('questions', 'Выбран как правильный'),
            'date' => Yii::t('questions', 'Дата'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getQuestion()
    {
        return $this->hasOne(Questions::className(), ['id' => 'question_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getQuestionsAnswersRatings()
    {
        return $this->hasMany(QuestionsAnswersRating::className(), ['answer_id' => 'id']);
    }

    public function getComments()
    {
        return $this->hasMany(QuestionsComments::className(), ['answer_id' => 'id'])
            ->where(['question_id' => $this->question_id]);
    }

    public function add($question_id)
    {
        $this->user_id = Yii::$app->user->id;
        $this->question_id = $question_id;
        $this->text = Questions::markdownParse($this->text);

        $question = Questions::findOne(['id' => $question_id]);
        $question->answers_count++;
        $question->save();

        if ($this->validate() && $this->save()) {
            return true;
        } else {
            return false;
        }
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes); // TODO: Change the autogenerated stub

        if (!$insert) {
            return true;
        }

        $activity = new Activity();
        $activity->main_id = $this->user_id;
        $activity->main_type  = Activity::MAIN_TYPE_USER;
        $activity->type = Activity::TYPE_NEW_QUESTION_ANSWER;
        $activity->data = json_encode([
            'username' => $this->user->username,
            'question_id' => $this->question_id,
            'parent_category_eng' => $this->question->category->parent->title_eng,
            'category_eng' => $this->question->category->title_eng,
            'title_eng' => $this->question->title_eng,
            'title' => $this->question->title,
        ]);
        $activity->save();
    }
}
