<?php

namespace App\Http\Controllers;

use App\Models\B2MockExam;
use App\Models\B2Exercise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class B2MockExamController extends Controller
{
    // Cambridge B2 First — structure:
    // Reading: 52 questions, 75 min (simplified: 10 Qs, 30 min)
    // Writing: 2 tasks, 80 min (simplified: 1 task, 20 min prompt shown)
    // Listening: 30 questions, 40 min (simplified: 10 Qs, 20 min)
    private const SECTIONS = [
        'reading' => ['questions' => 10, 'minutes' => 30, 'points_each' => 2],
        'listening' => ['questions' => 10, 'minutes' => 20, 'points_each' => 2],
        'writing' => ['questions' => 1, 'minutes' => 20, 'points_each' => 20],
    ];

    public function start(Request $request)
    {
        $user = Auth::user();

        // Abandon any in-progress exam
        B2MockExam::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->update(['status' => 'abandoned']);

        $exam = B2MockExam::create([
            'user_id' => $user->id,
            'status' => 'in_progress',
            'total_duration_minutes' => 150,
            'started_at' => now(),
        ]);

        // Fetch exercises per section
        $sections = [];
        foreach (self::SECTIONS as $skill => $config) {
            $exercises = B2Exercise::where('skill', $skill)
                ->inRandomOrder()
                ->limit($config['questions'])
                ->get(['id', 'question_text', 'user_input_instruction', 'options', 'category', 'difficulty', 'points_reward'])
                ->map(fn($e) => [
                    'id' => $e->id,
                    'question' => $e->question_text ?: $e->user_input_instruction,
                    'options' => $e->options,
                    'category' => $e->category,
                ]);
            $sections[$skill] = [
                'exercises' => $exercises,
                'minutes' => $config['minutes'],
                'question_count' => $config['questions'],
            ];
        }

        return response()->json([
            'exam_id' => $exam->id,
            'sections' => $sections,
            'total_minutes' => 70, // simplified total
            'instructions' => 'Complete all sections. Timer runs per section. Good luck!',
        ]);
    }

    public function submitSection(Request $request, int $examId)
    {
        $request->validate([
            'skill' => 'required|in:reading,listening,writing',
            'answers' => 'required|array',
        ]);

        $user = Auth::user();
        $exam = B2MockExam::where('id', $examId)->where('user_id', $user->id)->firstOrFail();

        $skill = $request->skill;
        $answers = $request->answers;
        $config = self::SECTIONS[$skill];

        // Grade answers
        $correct = 0;
        $results = [];
        if ($skill !== 'writing') {
            foreach ($answers as $exerciseId => $userAnswer) {
                $exercise = B2Exercise::find($exerciseId);
                if (!$exercise) continue;
                $isCorrect = strtolower(trim($userAnswer)) === strtolower(trim($exercise->correct_answer));
                if ($isCorrect) $correct++;
                $results[$exerciseId] = [
                    'correct' => $isCorrect,
                    'correct_answer' => $exercise->correct_answer,
                    'explanation' => $exercise->explanation,
                ];
            }
            $rawScore = $correct * $config['points_each'];
        } else {
            // Writing: heuristic score (will be improved with AI evaluation)
            $text = is_array($answers) ? implode(' ', $answers) : (string)$answers;
            $wordCount = str_word_count($text);
            $rawScore = min(20, max(0, (int)($wordCount / 9))); // 180 words ≈ 20 pts
            $results['writing'] = ['word_count' => $wordCount, 'score' => $rawScore];
        }

        // Normalize to Cambridge 0-100 per skill (will add up for total)
        $maxRaw = $config['questions'] * $config['points_each'];
        $normalizedScore = $maxRaw > 0 ? (int)(($rawScore / $maxRaw) * 100) : 0;

        // Save section score
        $sectionAnswers = $exam->section_answers ?? [];
        $sectionAnswers[$skill] = $answers;

        $sectionResults = $exam->section_results ?? [];
        $sectionResults[$skill] = $results;

        $exam->section_answers = $sectionAnswers;
        $exam->section_results = $sectionResults;
        $exam->{$skill . '_score'} = $normalizedScore;
        $exam->save();

        return response()->json([
            'skill' => $skill,
            'score' => $normalizedScore,
            'correct' => $correct,
            'total' => $config['questions'],
            'results' => $results,
        ]);
    }

    public function complete(Request $request, int $examId)
    {
        $user = Auth::user();
        $exam = B2MockExam::where('id', $examId)->where('user_id', $user->id)->firstOrFail();

        // Calculate total Cambridge scale score (scale: 0-300 → map to Cambridge 0-200)
        $reading = $exam->reading_score ?? 0;
        $writing = $exam->writing_score ?? 0;
        $listening = $exam->listening_score ?? 0;

        // Cambridge B2 First: each component ~60-80 points of total 230
        // Simplified: average × 2 to get approximate Cambridge score (100-200 range)
        $avgScore = ($reading + $writing + $listening) / 3;
        $cambridgeScore = (int)(($avgScore / 100) * 60 + 140); // maps 0-100 → 140-200

        $grade = B2MockExam::calculateGrade($cambridgeScore);
        $level = $exam->getCambridgeLevel();

        $exam->update([
            'status' => 'completed',
            'completed_at' => now(),
            'total_score' => $cambridgeScore,
            'grade' => $grade,
        ]);
        $exam->refresh();

        return response()->json([
            'exam_id' => $exam->id,
            'cambridge_score' => $cambridgeScore,
            'grade' => $grade,
            'level' => $level,
            'reading_score' => $exam->reading_score,
            'writing_score' => $exam->writing_score,
            'listening_score' => $exam->listening_score,
            'passed' => $cambridgeScore >= 160,
            'message' => $this->getResultMessage($grade, $cambridgeScore),
        ]);
    }

    public function getHistory()
    {
        $user = Auth::user();
        $exams = B2MockExam::where('user_id', $user->id)
            ->whereIn('status', ['completed'])
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get(['id', 'total_score', 'grade', 'reading_score', 'writing_score', 'listening_score', 'completed_at']);

        return response()->json(['exams' => $exams]);
    }

    private function getResultMessage(string $grade, int $score): string
    {
        return match($grade) {
            'A' => "Excelente! Puntuación sobresaliente. Estás listo para C1.",
            'B' => "Muy bien! Aprobado con distinción.",
            'C' => "Bien! Has pasado el examen B2.",
            'D' => "Cerca del aprobado. Sigue practicando.",
            default => "Necesitas más práctica. ¡Tú puedes!",
        };
    }
}
