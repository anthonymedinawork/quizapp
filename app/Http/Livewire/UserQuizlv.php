<?php

namespace App\Http\Livewire;

use App\Models\Quiz;
use Livewire\Component;
use App\Models\Question;
use App\Models\QuizHeader;

class UserQuizlv extends Component
{
    public $quizid;                     #Passed from parent view "quiz.blade.php" to this Livewire component
    public $quizSize;                   #Passed from parent view "quiz.blade.php" to this Livewire component
    public $count = 0;
    public $sectionId;
    public $quizPecentage;
    public $currentQuestion;
    public $userAnswered = [];
    public $showResult = false;
    public $currectQuizAnswers;
    public $totalQuizQuestions;
    public $quizInProgress = true;
    public $answeredQuestions = [];

    public function showResults()
    {
        // Get a count of total number of quiz questions in Quiz table for the just finisned quiz.
        $this->totalQuizQuestions = Quiz::where('quiz_header_id', $this->quizid->id)->count();

        // Get a count of correctly answered questions for this quiz.
        $this->currectQuizAnswers = Quiz::where('quiz_header_id', $this->quizid->id)
            ->where('is_correct', '1')
            ->count();

        // Caclculate score for upding the quiz_header table before finishing the quid.
        $this->quizPecentage = round(($this->currectQuizAnswers / $this->totalQuizQuestions) * 100, 2);

        // Push all the question ids to quiz_header table to retreve them while displaying the quiz details
        $this->quizid->questions_taken = serialize($this->answeredQuestions);

        // Update the status of quiz as completed, this is used to resuming any uncompleted/abondened quizzes 
        $this->quizid->completed = true;

        // Insert the quiz score to quiz_header table
        $this->quizid->score = $this->quizPecentage;

        // Save the udpates.
        $this->quizid->save();

        // Hide quiz div and show result div wrapped in if statements in the blade template.
        $this->showResult = true;
        $this->quizInProgress = false;
    }
    public function render()
    {
        return view('livewire.user-quizlv');
    }

    public function mount()
    {
        // Create a new quiz header in quiz_headers table and populate initial quiz information
        // Keep the instance in $this->quizid veriable for later updates to quiz.
        $this->quizid = QuizHeader::create([
            'user_id' => auth()->id(),
            'quiz_size' => $this->quizSize,
            'section_id' => $this->sectionId,
        ]);


        $this->count = 1;

        // Get the first/next question for the quiz.
        // Since we are using LiveWire component for quiz, the first quesiton and answers will be displayed through mount function.
        $this->currentQuestion = $this->getNextQuestion();
    }

    public function getNextQuestion()
    {
        //Return a random question from the selectoin selection by the user for quiz.
        $question = Question::where('section_id', $this->sectionId)
            ->whereNotIn('id', $this->answeredQuestions)
            ->with('answers')
            ->inRandomOrder()
            ->first();

        //If the quiz size is greater then actual questions available in the quiz sections,
        //Finish the quiz and take the user to results page on exhausting all question fro the slected section.
        if ($question === null) {
            //Update quiz size to curret count as we have ran out of quesitons and forcing user to end the quiz ;)
            $this->quizid->quiz_size = $this->count - 1;
            $this->quizid->save();
            return $this->showResults();
        }

        //Update the questions taken array so that we don't repeat same question again in the quiz
        //We feed this array into whereNotIn chain in getNextquestion() function.
        array_push($this->answeredQuestions, $question->id);
        return $question;
    }

    public function nextQuestion()
    {
        // Push all the question ids to quiz_header table to retreve them while displaying the quiz details
        $this->quizid->questions_taken = serialize($this->answeredQuestions);

        // Retrive the answer_id and value of answers clicked by the user and push them to Quiz table.
        list($answerId, $isChoiceCorrect) = explode(',', $this->userAnswered[0]);

        // Insert the current question_id, answer_id and whether it is correnct or wrong to quiz table.
        Quiz::create([
            'user_id' => auth()->id(),
            'quiz_header_id' => $this->quizid->id,
            'section_id' => $this->currentQuestion->section_id,
            'question_id' => $this->currentQuestion->id,
            'answer_id' => $answerId,
            'is_correct' => $isChoiceCorrect
        ]);

        // Save the record
        $this->quizid->save();

        // Increment the quiz counter so we terminate the quiz on the number of question user has selected during quiz creation.
        $this->count++;

        // Reset the veriables for next question
        $answerId = '';
        $isChoiceCorrect = '';
        $this->reset('userAnswered');

        // Finish the quiz when user has successfully taken all question in the quiz.
        if ($this->count == $this->quizSize + 1) {
            $this->showResults();
        }

        // Get a random questoin
        $this->currentQuestion = $this->getNextQuestion();
    }
}
