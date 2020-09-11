<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Survey;
use App\Models\Question;
use App\Models\Answer;

class SurveyController extends Controller
{
    public function all() {
        $surveies = Survey::all();
        $result = [];
        foreach($surveies as $s) {
            $result[] = [
                'title'         =>  $s->title,
                'population'    =>  $s->population->group_name,
                'language'      =>  $s->language,
                'views'         =>  $s->views,
                'data_created'  =>  $s->created_at
            ];
        }
        return response()->json([
            'message'   =>  'Get all surveies',
            'result'    =>  $result
        ]);
    }

    public function template(Request $request, $id, $utm) {
        dd('template');
    }

    public function edit($id) {
        $survey = Survey::find($id);
        $result = [];

        $result['survey'] = [
            'title'             =>  $survey->title,
            'intro'             =>  $survey->intro,
            'btn_start'         =>  $survey->btn_start,
            'btn_submit'        =>  $survey->btn_submit,
            'google_analytics'  =>  $survey->google_analytics,
            'facebook_pixel'    =>  $survey->facebook_pixel,
            'welcome_image'     =>  $survey->welcome_image,
            'population_id'     =>  $survey->population_id,
            'theme_id'          =>  $survey->theme_id,
            'language'          =>  $survey->language,
            'limit'             =>  $survey->limit,
            'views'             =>  $survey->views,
            'timer_min'         =>  $survey->timer_min,
            'timer_sec'         =>  $survey->timer_sec,
            'expired_at'        =>  $survey->expired_at,
            'auto_submit'       =>  $survey->auto_submit,
            'is_one_response'   =>  $survey->is_one_response,
            'questions'         =>  null,
        ];

        foreach($survey->questions as $q) {
            $question = [
                'survey_id'             =>  $q->survey_id,
                'type'                  =>  $q->type,
                'question'              =>  $q->question,
                'image'                 =>  $q->image,
                'order'                 =>  $q->order,
                'is_reliability'        =>  $q->is_reliability,
                'is_required'           =>  $q->is_required,
                'is_main'               =>  $q->is_main,
                'is_random'             =>  $q->is_random,
                'demographic'           =>  $q->demographic,
                'answer_limit'          =>  $q->answer_limit,
                'jump_id'               =>  $q->jump_id,
                'answers'               =>  null
            ];

            $answers = $q->answers;

            foreach($answers as $a) {
                $question['answers'][] = $a;
            }

            $result['survey']['questions'][] = $question;
        }
        return response()->json([
            'message'   =>  'Get the survey by id',
            'result'    =>  $result
        ]);
    }

    public function create(Request $request) {
        $message = '';
        $code = 200;
        $status = 'error';

        try {
            $params = $request->json()->all();

            // Begin Transaction
            DB::beginTransaction();
            $input_survey = $params['survey'];

            // Upload Welcome Image
            $welcome_image = upload_image($input_survey['welcome_image']);

            // New Survey
            $new_survey = new Survey;
            $new_survey->title = $input_survey['title'];
            $new_survey->intro = $input_survey['intro'];
            $new_survey->btn_start = $input_survey['btn_start'];
            $new_survey->btn_submit = $input_survey['btn_submit'];
            $new_survey->google_analytics = $input_survey['google_analytics'];
            $new_survey->facebook_pixel = $input_survey['facebook_pixel'];
            $new_survey->welcome_image = $welcome_image;
            $new_survey->population_id = $input_survey['population_id'];
            $new_survey->theme_id = $input_survey['theme_id'];
            $new_survey->language = $input_survey['language'];
            $new_survey->limit = $input_survey['limit'];
            $new_survey->timer_min = $input_survey['timer_min'];
            $new_survey->timer_sec = $input_survey['timer_sec'];
            $new_survey->expired_at = $input_survey['expired_at'];
            $new_survey->auto_submit = $input_survey['auto_submit'];
            $new_survey->is_one_response = $input_survey['is_one_response'];
            $new_survey->save();
            $survey_id = $new_survey->id;

            // New Question
            $input_questions = $input_survey['questions'];

            $answer_list = [];
            foreach($input_questions as $question) {
                // Upload Question Image
                $image = upload_image($question['image']);
                $type = $question['type'];
                $btn_text = '';
                $statement_btn_color = '';

                if($type == 'statement' || $type == 'thank-you') {
                    $btn_text = isset($question['btn_text'])?$question['btn_text']:'Continue';
                    $statement_btn_color = isset($question['statement_btn_color'])?$question['statement_btn_color']:'#404040';
                }

                $new_question_values = [
                    'survey_id'         =>  $survey_id,
                    'type'              =>  $type,
                    'question'          =>  $question['question'],
                    'image'             =>  $image,
                    'order'             =>  $question['order'],
                    'is_reliability'    =>  $question['is_reliability'],
                    'is_required'       =>  $question['is_required'],
                    'is_main'           =>  $question['is_main'],
                    'is_random'         =>  $question['is_random'],
                    'demographic'       =>  $question['demographic'],
                    'answer_limit'      =>  $question['answer_limit'],
                    'jump_id'           =>  $question['jump_id'],
                    'btn_text'          =>  $btn_text,
                    'statement_btn_color'=> $statement_btn_color,
                ];

                $new_question = Question::create($new_question_values);
                $question_id = $new_question->id;

                $input_answers = $question['answers'];
                foreach($input_answers as $answer) {
                    // Upload Answer Image
                    $answer_image = '';
                    if(isset($answer['image'])) {
                        $answer_image = upload_image($answer['image']);
                    }

                    $answer_list[] = [
                        'question_id'       =>  $question_id,
                        'content'           =>  $answer['content'],
                        'image'             =>  $answer_image,
                        'correct'           =>  $answer['correct'],
                        'jump_question_id'  =>  $answer['jump_question_id'],
                        'created_at'        =>  now()
                    ];
                }
            }

            if(isset($answer_list)) {
                Answer::insert($answer_list);
            }
            // Commit
            DB::commit();
            $message = 'New survey has been created successfully.';
            $status = 'success';
        } catch (\ErrorException $ex) {
            $status = 'error';
            $message = $ex->getMessage();
            $code = 451;

            DB::rollback();
        } catch( \Illuminate\Database\QueryException $qe) {
            $status = 'error';
            $message =$qe->errorInfo;
            $code = 400;

            DB::rollback();
        }

        return response()->json([
            'status'        =>  $status,
            'message'       =>  $message,
            'code'          =>  $code
        ]);
    }

    public function duplicate(Request $request, $id) {
        $message = '';
        $code = 200;
        $status = 'error';

        try {
            $survey = Survey::find($id);
            $result = [];
            // Begin Transaction
            DB::beginTransaction();

            $input_survey  = [
                'title'             =>  $survey->title,
                'intro'             =>  $survey->intro,
                'btn_start'         =>  $survey->btn_start,
                'btn_submit'        =>  $survey->btn_submit,
                'google_analytics'  =>  $survey->google_analytics,
                'facebook_pixel'    =>  $survey->facebook_pixel,
                'welcome_image'     =>  $survey->welcome_image,
                'population_id'     =>  $survey->population_id,
                'theme_id'          =>  $survey->theme_id,
                'language'          =>  $survey->language,
                'limit'             =>  $survey->limit,
                'views'             =>  $survey->views,
                'timer_min'         =>  $survey->timer_min,
                'timer_sec'         =>  $survey->timer_sec,
                'expired_at'        =>  $survey->expired_at,
                'auto_submit'       =>  $survey->auto_submit
            ];

            // New Survey
            $new_survey = new Survey;
            $new_survey->title = $input_survey['title'];
            $new_survey->intro = $input_survey['intro'];
            $new_survey->btn_start = $input_survey['btn_start'];
            $new_survey->btn_submit = $input_survey['btn_submit'];
            $new_survey->google_analytics = $input_survey['google_analytics'];
            $new_survey->facebook_pixel = $input_survey['facebook_pixel'];
            $new_survey->welcome_image = $input_survey['welcome_image'];
            $new_survey->population_id = $input_survey['population_id'];
            $new_survey->theme_id = $input_survey['theme_id'];
            $new_survey->language = $input_survey['language'];
            $new_survey->limit = $input_survey['limit'];
            $new_survey->timer_min = $input_survey['timer_min'];
            $new_survey->timer_sec = $input_survey['timer_sec'];
            $new_survey->expired_at = $input_survey['expired_at'];
            $new_survey->auto_submit = $input_survey['auto_submit'];
            $new_survey->save();
            $survey_id = $new_survey->id;

            // New Question
            foreach($survey->questions as $q) {
                $question = [
                    'survey_id'             =>  $q->survey_id,
                    'type'                  =>  $q->type,
                    'question'              =>  $q->question,
                    'image'                 =>  $q->image,
                    'order'                 =>  $q->order,
                    'is_reliability'        =>  $q->is_reliability,
                    'is_required'           =>  $q->is_required,
                    'is_main'               =>  $q->is_main,
                    'is_random'             =>  $q->is_random,
                    'demographic'           =>  $q->demographic,
                    'answer_limit'          =>  $q->answer_limit,
                    'jump_id'               =>  $q->jump_id,
                    'answers'               =>  null
                ];

                $answers = $q->answers;

                foreach($answers as $a) {
                    $question['answers'][] = $a;
                }

                $result['questions'][] = $question;
            }
            $input_questions = $result['questions'];

            $answer_list = [];
            foreach($input_questions as $question) {
                $image = $question['image']; // When duplicates, it does not need to upload the image as the base 64, just use the image url path
                $type = $question['type'];
                $btn_text = '';
                $statement_btn_color = '';

                if($type == 'statement' || $type == 'thank-you') {
                    $btn_text = isset($question['btn_text'])?$question['btn_text']:'Continue';
                    $statement_btn_color = isset($question['statement_btn_color'])?$question['statement_btn_color']:'#404040';
                }

                $new_question_values = [
                    'survey_id'         =>  $survey_id,
                    'type'              =>  $type,
                    'question'          =>  $question['question'],
                    'image'             =>  $image,
                    'order'             =>  $question['order'],
                    'is_reliability'    =>  $question['is_reliability'],
                    'is_required'       =>  $question['is_required'],
                    'is_main'           =>  $question['is_main'],
                    'is_random'         =>  $question['is_random'],
                    'demographic'       =>  $question['demographic'],
                    'answer_limit'      =>  $question['answer_limit'],
                    'jump_id'           =>  $question['jump_id'],
                    'btn_text'          =>  $btn_text,
                    'statement_btn_color'=> $statement_btn_color,
                ];

                $new_question = Question::create($new_question_values);
                $question_id = $new_question->id;

                $input_answers = $question['answers'];
                foreach($input_answers as $answer) {
                    // Upload Answer Image
                    $answer_image = '';
                    if(isset($answer['image'])) {
                        $answer_image = upload_image($answer['image']);
                    }

                    $answer_list[] = [
                        'question_id'       =>  $question_id,
                        'content'           =>  $answer['content'],
                        'image'             =>  $answer_image,
                        'correct'           =>  $answer['correct'],
                        'jump_question_id'  =>  $answer['jump_question_id'],
                        'created_at'        =>  now()
                    ];
                }
            }

            if(isset($answer_list)) {
                Answer::insert($answer_list);
            }
            // Commit
            DB::commit();
            $message = 'New survey has been duplicated successfully.';
            $status = 'success';
        } catch (\ErrorException $ex) {
            $status = 'error';
            $message = $ex->getMessage();
            $code = 451;

            DB::rollback();
        } catch( \Illuminate\Database\QueryException $qe) {
            $status = 'error';
            $message =$qe->errorInfo;
            $code = 400;

            DB::rollback();
        }

        return response()->json([
            'status'        =>  $status,
            'message'       =>  $message,
            'code'          =>  $code
        ]);
    }

    public function delete($id) {
        $survey = Survey::find($id);

        if ($survey != null)
        {
            $survey->delete();
            return response()->json([
                'status'        =>  'success',
                'message'       =>  'The survey has been deleted successfully',
                'code'          =>  200
            ]);
        } else {
            return response()->json([
                'status'        =>  'warning',
                'message'       =>  'The survey does not exist.',
                'code'          =>  404
            ]);
        }
    }
}
