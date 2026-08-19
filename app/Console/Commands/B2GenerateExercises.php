<?php

namespace App\Console\Commands;

use App\Models\B2Exercise;
use Illuminate\Console\Command;

class B2GenerateExercises extends Command
{
    protected $signature = 'b2:generate-exercises';
    protected $description = 'Generate and insert 200 B2 exercises into the database';

    public function handle(): void
    {
        $exercises = $this->getExercises();

        $this->info("Generating " . count($exercises) . " B2 exercises...");

        $imported = 0;
        $skipped = 0;

        foreach ($exercises as $ex) {
            $key = $ex['question_text'] ?? $ex['user_input_instruction'] ?? '';

            $exists = B2Exercise::where('skill', $ex['skill'])
                ->where(function ($q) use ($key, $ex) {
                    if (!empty($ex['question_text'])) {
                        $q->where('question_text', $ex['question_text']);
                    } else {
                        $q->where('user_input_instruction', $ex['user_input_instruction']);
                    }
                })
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            B2Exercise::create([
                'skill'                  => $ex['skill'],
                'type'                   => $ex['type'],
                'question_text'          => $ex['question_text'] ?? '',
                'options'                => $ex['options'] ?? null,
                'correct_answer'         => $ex['correct_answer'] ?? '',
                'explanation'            => $ex['explanation'] ?? null,
                'user_input_instruction' => $ex['user_input_instruction'] ?? null,
                'difficulty'             => 4,
                'points_reward'          => 4,
                'category'              => $ex['category'] ?? null,
                'source'                => 'Cambridge',
                'is_active'             => true,
            ]);

            $imported++;
        }

        $this->line("✓ {$imported} exercises imported, {$skipped} already existed");
        $this->info('✅ Generated ' . $imported . ' exercises');

        $this->table(
            ['Skill', 'Count'],
            B2Exercise::selectRaw('skill, COUNT(*) as count')
                ->groupBy('skill')
                ->get()
                ->map(fn($r) => [$r->skill, $r->count])
                ->toArray()
        );
    }

    private function getExercises(): array
    {
        return array_merge(
            $this->grammarPhrasalVerbs(),
            $this->grammarWordFormation(),
            $this->grammarTenses(),
            $this->grammarPrepositions(),
            $this->grammarOther(),
            $this->readingComprehension(),
            $this->readingVocabulary(),
            $this->listeningDialogues(),
            $this->listeningMonologues(),
            $this->writingExercises(),
            $this->speakingExercises()
        );
    }

    private function grammarPhrasalVerbs(): array
    {
        return [
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'She finally ___ her new job after years of looking.',
                'options' => ['took on', 'took over', 'took up', 'took out'],
                'correct_answer' => 'took on',
                'explanation' => '"Take on" means to accept or start doing a job or task.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'Can you help me ___ this document? I need to find specific figures.',
                'options' => ['go through', 'go over', 'go past', 'go along'],
                'correct_answer' => 'go through',
                'explanation' => '"Go through" means to examine or check something carefully.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'I ___ my old university friend while shopping yesterday.',
                'options' => ['ran into', 'ran over', 'ran away', 'ran out'],
                'correct_answer' => 'ran into',
                'explanation' => '"Run into" means to meet someone unexpectedly.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'We need to ___ all the options before making a final decision.',
                'options' => ['look into', 'look up', 'look over', 'look out'],
                'correct_answer' => 'look into',
                'explanation' => '"Look into" means to investigate or examine something.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'The meeting was ___ due to the manager\'s sudden illness.',
                'options' => ['called off', 'called up', 'called out', 'called on'],
                'correct_answer' => 'called off',
                'explanation' => '"Call off" means to cancel a planned event.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'She ___ her presentation skills through regular practice.',
                'options' => ['built up', 'built in', 'built on', 'built out'],
                'correct_answer' => 'built up',
                'explanation' => '"Build up" means to develop or strengthen something gradually.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'He ___ a brilliant idea during the brainstorming session.',
                'options' => ['came up with', 'came across', 'came about', 'came forward'],
                'correct_answer' => 'came up with',
                'explanation' => '"Come up with" means to think of or produce an idea.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'She ___ the opportunity to study abroad because of family issues.',
                'options' => ['turned down', 'turned up', 'turned out', 'turned over'],
                'correct_answer' => 'turned down',
                'explanation' => '"Turn down" means to refuse or reject an offer or invitation.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'Please ___ the registration form and hand it to reception.',
                'options' => ['fill in', 'fill up', 'fill out', 'fill off'],
                'correct_answer' => 'fill in',
                'explanation' => '"Fill in" means to write information in the spaces on a form.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'The project was ___ until next quarter because of budget cuts.',
                'options' => ['put off', 'put up', 'put away', 'put out'],
                'correct_answer' => 'put off',
                'explanation' => '"Put off" means to delay or postpone something.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'It was hard, but she finally ___ her fear of public speaking.',
                'options' => ['got over', 'got through', 'got on', 'got by'],
                'correct_answer' => 'got over',
                'explanation' => '"Get over" means to recover from an illness, shock, or bad experience.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'He ___ his resignation after disagreements with management.',
                'options' => ['handed in', 'handed out', 'handed over', 'handed back'],
                'correct_answer' => 'handed in',
                'explanation' => '"Hand in" means to give something to someone in authority.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'The company had to ___ 150 employees due to financial losses.',
                'options' => ['lay off', 'lay down', 'lay out', 'lay back'],
                'correct_answer' => 'lay off',
                'explanation' => '"Lay off" means to dismiss employees because there is not enough work.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'She ___ her old notes before the important presentation.',
                'options' => ['went over', 'went through', 'went back to', 'went along with'],
                'correct_answer' => 'went over',
                'explanation' => '"Go over" means to review or examine something.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'The traffic ___ us and we arrived thirty minutes late.',
                'options' => ['held up', 'held back', 'held in', 'held on'],
                'correct_answer' => 'held up',
                'explanation' => '"Hold up" means to delay or prevent progress.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'He ___ running marathons after recovering from his knee injury.',
                'options' => ['took up', 'took on', 'took in', 'took off'],
                'correct_answer' => 'took up',
                'explanation' => '"Take up" means to start doing a new activity or hobby.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'She ___ working late for three months before the project was complete.',
                'options' => ['kept on', 'kept up', 'kept in', 'kept to'],
                'correct_answer' => 'kept on',
                'explanation' => '"Keep on" means to continue doing something.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'The manager ___ a plan to improve team productivity.',
                'options' => ['put forward', 'put off', 'put up', 'put away'],
                'correct_answer' => 'put forward',
                'explanation' => '"Put forward" means to suggest an idea or plan for consideration.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'She ___ the document and spotted several errors.',
                'options' => ['looked over', 'looked up', 'looked into', 'looked out'],
                'correct_answer' => 'looked over',
                'explanation' => '"Look over" means to examine or check something quickly.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'He ___ some old photos while clearing out the attic.',
                'options' => ['came across', 'came up', 'came in', 'came about'],
                'correct_answer' => 'came across',
                'explanation' => '"Come across" means to find or discover something unexpectedly.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'She ___ her old habits as soon as she moved to the new city.',
                'options' => ['gave up', 'gave in', 'gave out', 'gave away'],
                'correct_answer' => 'gave up',
                'explanation' => '"Give up" means to stop doing something or to abandon a habit.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'He ___ his promises as soon as things became difficult.',
                'options' => ['went back on', 'went through with', 'went ahead with', 'went over'],
                'correct_answer' => 'went back on',
                'explanation' => '"Go back on" means to fail to keep a promise or agreement.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'The new employee ___ with the rest of the team very quickly.',
                'options' => ['fitted in', 'fitted out', 'fitted up', 'fitted back'],
                'correct_answer' => 'fitted in',
                'explanation' => '"Fit in" means to feel comfortable and accepted in a group.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'She ___ a new language by listening to podcasts every day.',
                'options' => ['picked up', 'picked out', 'picked on', 'picked over'],
                'correct_answer' => 'picked up',
                'explanation' => '"Pick up" means to learn something informally without a teacher.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'He ___ the issue of overtime pay during the team meeting.',
                'options' => ['brought up', 'came up with', 'put forward', 'pointed out'],
                'correct_answer' => 'brought up',
                'explanation' => '"Bring up" means to mention a topic in a conversation.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'She ___ with her business partner after a financial disagreement.',
                'options' => ['fell out', 'fell through', 'fell behind', 'fell back'],
                'correct_answer' => 'fell out',
                'explanation' => '"Fall out" means to have a quarrel with someone.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'He ___ working on the project even when it seemed impossible.',
                'options' => ['pressed on', 'pressed for', 'pressed up', 'pressed out'],
                'correct_answer' => 'pressed on',
                'explanation' => '"Press on" means to continue doing something with determination.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'She ___ from the competition due to an unexpected injury.',
                'options' => ['pulled out', 'pulled off', 'pulled up', 'pulled in'],
                'correct_answer' => 'pulled out',
                'explanation' => '"Pull out" means to withdraw from an activity or agreement.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'They decided to ___ the old warehouse and build a new office block.',
                'options' => ['knock down', 'knock off', 'knock out', 'knock back'],
                'correct_answer' => 'knock down',
                'explanation' => '"Knock down" means to demolish a building or structure.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'phrasal_verbs',
                'question_text' => 'The local authorities decided to ___ the abandoned factory to build a park.',
                'options' => ['tear down', 'tear up', 'tear apart', 'tear away'],
                'correct_answer' => 'tear down',
                'explanation' => '"Tear down" means to demolish a building or structure.',
            ],
        ];
    }

    private function grammarWordFormation(): array
    {
        return [
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'Her ___ in the field of medicine has been remarkable. (succeed)',
                'options' => ['success', 'succession', 'successful', 'successfully'],
                'correct_answer' => 'success',
                'explanation' => 'The noun "success" is needed here as the subject of the sentence.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'The customers expressed great ___ with the quality of service. (satisfy)',
                'options' => ['satisfaction', 'satisfying', 'satisfied', 'satisfactory'],
                'correct_answer' => 'satisfaction',
                'explanation' => '"Satisfaction" is the noun form needed after "expressed great".',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'The ___ of the new bridge took three years. (construct)',
                'options' => ['construction', 'constructing', 'constructive', 'constructor'],
                'correct_answer' => 'construction',
                'explanation' => '"Construction" is the noun form of "construct".',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'Winning the award was a major ___ for the whole team. (achieve)',
                'options' => ['achievement', 'achieving', 'achiever', 'achievable'],
                'correct_answer' => 'achievement',
                'explanation' => '"Achievement" is the noun form needed here.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'There has been significant ___ in renewable energy technology. (improve)',
                'options' => ['improvement', 'improving', 'improved', 'improvable'],
                'correct_answer' => 'improvement',
                'explanation' => '"Improvement" is the noun form of "improve".',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'His ___ of the subject was impressive for someone his age. (know)',
                'options' => ['knowledge', 'knowing', 'known', 'knowledgeable'],
                'correct_answer' => 'knowledge',
                'explanation' => '"Knowledge" is the noun form needed after "His".',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'She found it difficult to make a ___ between the two job offers. (decide)',
                'options' => ['decision', 'decisive', 'decided', 'decisively'],
                'correct_answer' => 'decision',
                'explanation' => '"Decision" is the noun form of "decide".',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'Building ___ takes years of practice in any new skill. (confident)',
                'options' => ['confidence', 'confidently', 'confidential', 'confide'],
                'correct_answer' => 'confidence',
                'explanation' => '"Confidence" is the noun form needed here.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'The ___ of the event required months of careful planning. (organise)',
                'options' => ['organisation', 'organising', 'organised', 'organisational'],
                'correct_answer' => 'organisation',
                'explanation' => '"Organisation" is the noun form of "organise".',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'Taking ___ for your mistakes is a sign of maturity. (responsible)',
                'options' => ['responsibility', 'responsibly', 'responsive', 'responsiveness'],
                'correct_answer' => 'responsibility',
                'explanation' => '"Responsibility" is the noun form of "responsible".',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'Her artistic ___ was evident from a very young age. (create)',
                'options' => ['creativity', 'creative', 'creation', 'creatively'],
                'correct_answer' => 'creativity',
                'explanation' => '"Creativity" is the abstract noun needed here.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'The engineers finally found a ___ to the technical problem. (solve)',
                'options' => ['solution', 'solving', 'solvable', 'solver'],
                'correct_answer' => 'solution',
                'explanation' => '"Solution" is the noun form of "solve".',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'Quality ___ is essential for career advancement in any field. (educate)',
                'options' => ['education', 'educational', 'educated', 'educating'],
                'correct_answer' => 'education',
                'explanation' => '"Education" is the noun form of "educate".',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'The ___ between the two companies has lasted over twenty years. (relate)',
                'options' => ['relationship', 'relation', 'relating', 'relatable'],
                'correct_answer' => 'relationship',
                'explanation' => '"Relationship" refers to a connection between people or organisations.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'Her professional ___ made a great impression at the interview. (appear)',
                'options' => ['appearance', 'appearing', 'apparent', 'apparently'],
                'correct_answer' => 'appearance',
                'explanation' => '"Appearance" is the noun form of "appear".',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'The manager had high ___ for the new marketing campaign. (expect)',
                'options' => ['expectations', 'expecting', 'expected', 'expectantly'],
                'correct_answer' => 'expectations',
                'explanation' => '"Expectations" (plural noun) is needed here.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'Her ___ to the local charity has been invaluable. (contribute)',
                'options' => ['contribution', 'contributing', 'contributed', 'contributory'],
                'correct_answer' => 'contribution',
                'explanation' => '"Contribution" is the noun form of "contribute".',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'The two firms formed a successful business ___. (partner)',
                'options' => ['partnership', 'partnering', 'partnered', 'partnerlike'],
                'correct_answer' => 'partnership',
                'explanation' => '"Partnership" means a business owned by two or more people.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'The new software greatly improved the ___ of our workflow. (efficient)',
                'options' => ['efficiency', 'efficiently', 'efficient', 'efficiencies'],
                'correct_answer' => 'efficiency',
                'explanation' => '"Efficiency" is the noun form of "efficient".',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'word_formation',
                'question_text' => 'A thorough ___ of the contract revealed several ambiguities. (understand)',
                'options' => ['understanding', 'understood', 'understandable', 'understands'],
                'correct_answer' => 'understanding',
                'explanation' => '"Understanding" used as a noun means comprehension or knowledge.',
            ],
        ];
    }

    private function grammarTenses(): array
    {
        return [
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'By the time she retires, she ___ for the company for 35 years.',
                'options' => ['will have worked', 'will be working', 'will work', 'would have worked'],
                'correct_answer' => 'will have worked',
                'explanation' => 'Future perfect is used for an action that will be completed before a future point.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'If she ___ the meeting, she would have received the important information.',
                'options' => ['had attended', 'attended', 'has attended', 'would attend'],
                'correct_answer' => 'had attended',
                'explanation' => 'Third conditional uses "had + past participle" for an unreal past situation.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'By 2030, scientists ___ on this research project for over a decade.',
                'options' => ['will have been working', 'will be working', 'will work', 'have been working'],
                'correct_answer' => 'will have been working',
                'explanation' => 'Future perfect continuous emphasises the duration of an ongoing activity up to a future time.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'When she arrived at the station, the train ___.',
                'options' => ['had already left', 'has already left', 'already left', 'was already leaving'],
                'correct_answer' => 'had already left',
                'explanation' => 'Past perfect is used for an action that occurred before another past action.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'At this time tomorrow, she ___ her final exam.',
                'options' => ['will be taking', 'will take', 'is taking', 'will have taken'],
                'correct_answer' => 'will be taking',
                'explanation' => 'Future continuous is used for an action in progress at a specific future time.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'If I ___ more time, I would learn a second language.',
                'options' => ['had', 'have', 'would have', 'had had'],
                'correct_answer' => 'had',
                'explanation' => 'Second conditional uses "past simple" to talk about an unreal present situation.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'The new library ___ by the end of next year.',
                'options' => ['will have been built', 'will be built', 'will have built', 'is being built'],
                'correct_answer' => 'will have been built',
                'explanation' => 'Future perfect passive is used for a passive action completed before a future time.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'I wish I ___ speak Japanese fluently.',
                'options' => ['could', 'can', 'would', 'should'],
                'correct_answer' => 'could',
                'explanation' => '"Wish + could" expresses a desire for an unreal ability in the present.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'When she was a child, she ___ spend hours reading in the garden.',
                'options' => ['used to', 'was used to', 'is used to', 'would be used to'],
                'correct_answer' => 'used to',
                'explanation' => '"Used to + infinitive" expresses a past habit or state that no longer exists.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'She told me she ___ the report the following day.',
                'options' => ['would submit', 'will submit', 'is submitting', 'submits'],
                'correct_answer' => 'would submit',
                'explanation' => 'In reported speech, "will" changes to "would" when the reporting verb is past.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'Water ___ at 100 degrees Celsius at sea level.',
                'options' => ['boils', 'is boiling', 'boiled', 'will boil'],
                'correct_answer' => 'boils',
                'explanation' => 'Present simple is used for scientific facts and general truths.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'He ___ his phone when the director suddenly walked in.',
                'options' => ['was checking', 'checked', 'has checked', 'had checked'],
                'correct_answer' => 'was checking',
                'explanation' => 'Past continuous is used for an action in progress when another past action occurred.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'It ___ heavily tomorrow morning according to the forecast.',
                'options' => ['is going to rain', 'rains', 'has rained', 'was raining'],
                'correct_answer' => 'is going to rain',
                'explanation' => '"Going to" is used for a future event based on current evidence or plans.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'The documents ___ before the meeting begins.',
                'options' => ['must be signed', 'must sign', 'must have signed', 'are to sign'],
                'correct_answer' => 'must be signed',
                'explanation' => 'Modal passive "must be + past participle" expresses obligation in passive form.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'tenses',
                'question_text' => 'I regret ___ so much time on social media last year.',
                'options' => ['spending', 'to spend', 'spent', 'having spend'],
                'correct_answer' => 'spending',
                'explanation' => '"Regret + gerund" refers to regretting a past action.',
            ],
        ];
    }

    private function grammarPrepositions(): array
    {
        return [
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'prepositions',
                'question_text' => 'She is very keen ___ learning new languages.',
                'options' => ['on', 'in', 'at', 'for'],
                'correct_answer' => 'on',
                'explanation' => '"Keen on" is the correct prepositional phrase meaning enthusiastic about.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'prepositions',
                'question_text' => 'He has been waiting ___ a response for over two weeks.',
                'options' => ['for', 'on', 'at', 'to'],
                'correct_answer' => 'for',
                'explanation' => '"Wait for" requires the preposition "for".',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'prepositions',
                'question_text' => 'She is really good ___ maths and sciences.',
                'options' => ['at', 'in', 'on', 'for'],
                'correct_answer' => 'at',
                'explanation' => '"Good at" is the correct prepositional phrase when describing a skill.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'prepositions',
                'question_text' => 'The decision depends ___ several factors, including budget.',
                'options' => ['on', 'at', 'for', 'to'],
                'correct_answer' => 'on',
                'explanation' => '"Depend on" is the correct prepositional phrase.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'prepositions',
                'question_text' => 'He was charged ___ theft and sentenced to two years in prison.',
                'options' => ['with', 'for', 'of', 'by'],
                'correct_answer' => 'with',
                'explanation' => '"Charged with" is used when formally accused of a crime.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'prepositions',
                'question_text' => 'She applied ___ the position of marketing director.',
                'options' => ['for', 'to', 'at', 'on'],
                'correct_answer' => 'for',
                'explanation' => '"Apply for" is used when seeking a job or position.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'prepositions',
                'question_text' => 'He is very dedicated ___ his work and rarely takes breaks.',
                'options' => ['to', 'for', 'at', 'on'],
                'correct_answer' => 'to',
                'explanation' => '"Dedicated to" is the correct prepositional phrase.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'prepositions',
                'question_text' => 'The seminar will take place ___ the city conference centre.',
                'options' => ['at', 'in', 'on', 'by'],
                'correct_answer' => 'at',
                'explanation' => '"At" is used for specific locations and events.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'prepositions',
                'question_text' => 'She is very enthusiastic ___ the proposed changes.',
                'options' => ['about', 'for', 'at', 'to'],
                'correct_answer' => 'about',
                'explanation' => '"Enthusiastic about" is the correct prepositional phrase.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'prepositions',
                'question_text' => 'The report consists ___ five main sections and an appendix.',
                'options' => ['of', 'in', 'from', 'with'],
                'correct_answer' => 'of',
                'explanation' => '"Consist of" is used to describe components or parts of something.',
            ],
        ];
    }

    private function grammarOther(): array
    {
        return [
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'other',
                'question_text' => 'Before the presentation, he paused to ___ a deep breath.',
                'options' => ['take', 'make', 'do', 'have'],
                'correct_answer' => 'take',
                'explanation' => '"Take a deep breath" is the correct collocation.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'other',
                'question_text' => 'Would you ___ doing the presentation instead of me?',
                'options' => ['mind', 'like', 'enjoy', 'prefer'],
                'correct_answer' => 'mind',
                'explanation' => '"Would you mind + gerund?" is used to make polite requests.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'other',
                'question_text' => 'Please ___ sure all documents are submitted by Friday.',
                'options' => ['make', 'do', 'be', 'take'],
                'correct_answer' => 'make',
                'explanation' => '"Make sure" is the correct collocation meaning to ensure.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'other',
                'question_text' => 'The speaker ___ attention to several important points during the lecture.',
                'options' => ['drew', 'made', 'paid', 'took'],
                'correct_answer' => 'drew',
                'explanation' => '"Draw attention to" is the correct collocation.',
            ],
            [
                'skill' => 'grammar', 'type' => 'multiple_choice', 'category' => 'other',
                'question_text' => 'The ceremony will ___ place at the city hall on Saturday evening.',
                'options' => ['take', 'make', 'have', 'give'],
                'correct_answer' => 'take',
                'explanation' => '"Take place" is the correct phrase meaning to happen or occur.',
            ],
        ];
    }

    private function readingComprehension(): array
    {
        $passages = [
            [
                'passage' => 'Social media platforms have fundamentally changed the way people communicate and share information. While these platforms offer unprecedented opportunities for connection, they have also raised serious concerns about misinformation, privacy, and mental health. Research suggests that excessive social media use can lead to feelings of anxiety, depression, and social isolation, particularly among younger users. However, when used thoughtfully, social media can foster meaningful relationships and provide access to valuable resources and communities.',
                'questions' => [
                    ['q' => 'According to the passage, what is one negative effect of excessive social media use?', 'a' => 'anxiety and depression', 'opts' => ['anxiety and depression', 'improved communication', 'better access to resources', 'stronger communities']],
                    ['q' => 'The passage suggests that social media can be beneficial when?', 'a' => 'used thoughtfully', 'opts' => ['used continuously', 'used thoughtfully', 'avoided completely', 'used by adults only']],
                    ['q' => 'What does "unprecedented" most likely mean in this context?', 'a' => 'never seen before', 'opts' => ['never seen before', 'very small', 'well-known', 'regularly occurring']],
                ],
            ],
            [
                'passage' => 'Climate change represents one of the most significant challenges facing humanity in the twenty-first century. Rising global temperatures, caused primarily by greenhouse gas emissions from human activities, are leading to more frequent extreme weather events, rising sea levels, and disruption of ecosystems worldwide. Scientists argue that immediate and decisive action is required to limit warming to 1.5 degrees Celsius above pre-industrial levels. This would require a complete transformation of energy systems, transportation, agriculture, and industry.',
                'questions' => [
                    ['q' => 'What is the primary cause of rising global temperatures according to the passage?', 'a' => 'greenhouse gas emissions', 'opts' => ['greenhouse gas emissions', 'solar activity', 'volcanic eruptions', 'ocean currents']],
                    ['q' => 'What temperature limit do scientists recommend?', 'a' => '1.5 degrees Celsius', 'opts' => ['1.5 degrees Celsius', '2.5 degrees Celsius', '3 degrees Celsius', '1 degree Celsius']],
                    ['q' => 'Which sector is NOT mentioned as needing transformation?', 'a' => 'healthcare', 'opts' => ['healthcare', 'energy systems', 'transportation', 'agriculture']],
                ],
            ],
            [
                'passage' => 'Remote working has become increasingly common following the global pandemic of the early 2020s. Many companies discovered that employees could be just as productive working from home as in a traditional office environment. This shift has brought numerous benefits, including reduced commuting time, greater flexibility, and lower office costs for businesses. However, challenges remain, particularly around maintaining team cohesion, mental health support for isolated workers, and ensuring fair access to technology for all employees.',
                'questions' => [
                    ['q' => 'What event accelerated the adoption of remote working?', 'a' => 'the global pandemic', 'opts' => ['the global pandemic', 'advances in technology', 'rising office costs', 'government policies']],
                    ['q' => 'Which is listed as a benefit of remote working for businesses?', 'a' => 'lower office costs', 'opts' => ['lower office costs', 'easier management', 'better productivity metrics', 'faster internet']],
                    ['q' => 'What challenge related to workers is mentioned?', 'a' => 'mental health support for isolated workers', 'opts' => ['mental health support for isolated workers', 'lack of computer skills', 'longer working hours', 'reduced salaries']],
                ],
            ],
            [
                'passage' => 'Regular physical exercise has long been associated with improved physical health, but recent research highlights its significant benefits for mental wellbeing too. Studies show that aerobic exercise in particular can reduce symptoms of anxiety and depression, improve sleep quality, and boost cognitive function. Even moderate exercise, such as a daily 30-minute walk, can have measurable positive effects on mood and mental clarity. Health professionals increasingly recommend exercise as a complement to traditional mental health treatments.',
                'questions' => [
                    ['q' => 'What type of exercise is specifically highlighted as beneficial?', 'a' => 'aerobic exercise', 'opts' => ['aerobic exercise', 'weight training', 'yoga', 'swimming']],
                    ['q' => 'How much moderate exercise can have positive effects according to the passage?', 'a' => 'a daily 30-minute walk', 'opts' => ['a daily 30-minute walk', 'two hours of exercise', 'weekly gym sessions', 'ten minutes a day']],
                    ['q' => 'How do health professionals view exercise in relation to mental health treatment?', 'a' => 'as a complement', 'opts' => ['as a complement', 'as a replacement', 'as unnecessary', 'as dangerous']],
                ],
            ],
            [
                'passage' => 'Online learning platforms have transformed access to education globally. Students in remote areas can now study courses from prestigious universities, and working professionals can upgrade their skills without interrupting their careers. However, critics point out that online learning requires significant self-discipline and motivation, which not all students possess. Furthermore, the lack of face-to-face interaction can make it difficult to build practical skills in fields like medicine or engineering, where hands-on experience is essential.',
                'questions' => [
                    ['q' => 'What advantage does online learning offer to people in remote areas?', 'a' => 'access to courses from prestigious universities', 'opts' => ['access to courses from prestigious universities', 'free education', 'guaranteed employment', 'shorter course duration']],
                    ['q' => 'What personal quality do critics say is required for online learning?', 'a' => 'self-discipline', 'opts' => ['self-discipline', 'technical expertise', 'social skills', 'prior qualifications']],
                    ['q' => 'In which fields is hands-on experience said to be essential?', 'a' => 'medicine and engineering', 'opts' => ['medicine and engineering', 'arts and literature', 'business and finance', 'history and philosophy']],
                ],
            ],
            [
                'passage' => 'The streaming revolution has fundamentally altered how people consume entertainment. Gone are the days when audiences would gather around the television at a fixed time to watch a favourite programme. Today, subscribers can watch what they want, when they want, on virtually any device. This change has empowered viewers but has also created new challenges for content creators, who must now compete in a crowded global market. Original programming has become a key battleground, with streaming services investing billions in exclusive content.',
                'questions' => [
                    ['q' => 'How has streaming changed viewing habits?', 'a' => 'viewers can watch any time on any device', 'opts' => ['viewers can watch any time on any device', 'viewing has become more social', 'fewer programmes are produced', 'television sets are more expensive']],
                    ['q' => 'What challenge do content creators face in the streaming era?', 'a' => 'competing in a crowded global market', 'opts' => ['competing in a crowded global market', 'lack of technology', 'limited budgets', 'government restrictions']],
                    ['q' => 'What have streaming services invested heavily in?', 'a' => 'original programming', 'opts' => ['original programming', 'advertising', 'equipment', 'research']],
                ],
            ],
            [
                'passage' => 'Sustainable tourism has emerged as a response to growing concerns about the environmental and cultural impact of mass tourism. Unlike conventional tourism, which prioritises profit and visitor numbers, sustainable tourism aims to minimise negative impacts while maximising benefits for local communities and ecosystems. Travellers who embrace sustainable practices might choose locally owned accommodation, avoid single-use plastics, and respect local customs and wildlife. While sustainable tourism is gaining popularity, it remains a niche market compared to mainstream travel.',
                'questions' => [
                    ['q' => 'What does sustainable tourism prioritise?', 'a' => 'minimising negative impacts and benefiting local communities', 'opts' => ['minimising negative impacts and benefiting local communities', 'maximising visitor numbers', 'reducing travel costs', 'promoting luxury experiences']],
                    ['q' => 'Which of the following is an example of sustainable travel practice?', 'a' => 'choosing locally owned accommodation', 'opts' => ['choosing locally owned accommodation', 'flying frequently', 'staying in international hotel chains', 'visiting crowded tourist hotspots']],
                    ['q' => 'How does sustainable tourism compare to mainstream travel currently?', 'a' => 'it remains a niche market', 'opts' => ['it remains a niche market', 'it is the dominant form of travel', 'it is growing faster than expected', 'it is declining in popularity']],
                ],
            ],
            [
                'passage' => 'Artificial intelligence is increasingly being used in medicine to assist with diagnosis and treatment planning. AI algorithms can analyse medical images such as X-rays and MRI scans with impressive accuracy, sometimes outperforming experienced radiologists. These systems can process vast quantities of data quickly and identify patterns that may be invisible to the human eye. However, many medical professionals stress that AI should serve as a support tool rather than replacing human judgement, particularly in complex cases requiring empathy and ethical consideration.',
                'questions' => [
                    ['q' => 'What kind of medical data can AI analyse effectively?', 'a' => 'medical images like X-rays and MRI scans', 'opts' => ['medical images like X-rays and MRI scans', 'patient interviews', 'surgical records', 'prescription histories']],
                    ['q' => 'How do medical professionals believe AI should be used?', 'a' => 'as a support tool, not a replacement for human judgement', 'opts' => ['as a support tool, not a replacement for human judgement', 'as the primary decision maker', 'only for administrative tasks', 'exclusively for research purposes']],
                    ['q' => 'What advantage does AI have over humans when processing data?', 'a' => 'speed and ability to identify invisible patterns', 'opts' => ['speed and ability to identify invisible patterns', 'better empathy', 'lower costs', 'more accuracy in all cases']],
                ],
            ],
            [
                'passage' => 'Urban gardening is gaining popularity in cities around the world as people seek ways to reconnect with nature and produce their own food. From rooftop gardens to community allotments, urban green spaces offer a range of benefits: they improve air quality, reduce the urban heat island effect, support biodiversity, and provide communities with fresh produce. Some urban farms even supply local restaurants and markets. Despite space constraints and the initial cost of setting up, urban gardening is seen by many as an important step towards more sustainable cities.',
                'questions' => [
                    ['q' => 'What is one environmental benefit of urban gardening?', 'a' => 'reduces the urban heat island effect', 'opts' => ['reduces the urban heat island effect', 'increases property values', 'reduces crime rates', 'improves road infrastructure']],
                    ['q' => 'Who do some urban farms supply?', 'a' => 'local restaurants and markets', 'opts' => ['local restaurants and markets', 'supermarket chains', 'government canteens', 'international export']],
                    ['q' => 'What is mentioned as a challenge of urban gardening?', 'a' => 'space constraints and initial setup costs', 'opts' => ['space constraints and initial setup costs', 'lack of community interest', 'government regulation', 'water shortages']],
                ],
            ],
            [
                'passage' => 'Research into bilingualism has revealed surprising cognitive benefits for people who speak two languages fluently. Studies suggest that bilingual individuals tend to have better executive function, including superior abilities in attention control, task-switching, and problem-solving. Some research even indicates that being bilingual may delay the onset of dementia in later life. However, these findings are not without controversy, as some researchers argue that the "bilingual advantage" has been overstated and that social and educational factors play a greater role in cognitive ability.',
                'questions' => [
                    ['q' => 'What cognitive skill is associated with bilingualism?', 'a' => 'better attention control', 'opts' => ['better attention control', 'higher IQ scores', 'faster language learning', 'better memory for names']],
                    ['q' => 'What health benefit might bilingualism offer in later life?', 'a' => 'delayed onset of dementia', 'opts' => ['delayed onset of dementia', 'lower risk of heart disease', 'improved physical fitness', 'better eyesight']],
                    ['q' => 'What do some researchers argue about the bilingual advantage?', 'a' => 'it has been overstated', 'opts' => ['it has been overstated', 'it is greater than thought', 'it only applies to children', 'it has been proven conclusively']],
                ],
            ],
        ];

        $exercises = [];
        foreach ($passages as $p) {
            foreach ($p['questions'] as $q) {
                $exercises[] = [
                    'skill' => 'reading',
                    'type' => 'reading_comprehension',
                    'category' => 'reading_comprehension',
                    'question_text' => $p['passage'] . "\n\nQuestion: " . $q['q'],
                    'options' => $q['opts'],
                    'correct_answer' => $q['a'],
                    'explanation' => 'Based on the information provided in the reading passage.',
                ];
            }
        }

        return $exercises;
    }

    private function readingVocabulary(): array
    {
        return [
            [
                'skill' => 'reading', 'type' => 'vocabulary', 'category' => 'vocabulary_in_context',
                'question_text' => 'The new policy was met with considerable ___; many staff refused to comply. (opposition)',
                'options' => ['resistance', 'assistance', 'insistence', 'persistence'],
                'correct_answer' => 'resistance',
                'explanation' => '"Resistance" means opposition or refusal to comply.',
            ],
            [
                'skill' => 'reading', 'type' => 'vocabulary', 'category' => 'vocabulary_in_context',
                'question_text' => 'The scientist made a ___ discovery that could change medicine forever.',
                'options' => ['groundbreaking', 'mundane', 'expected', 'delayed'],
                'correct_answer' => 'groundbreaking',
                'explanation' => '"Groundbreaking" means innovative and pioneering.',
            ],
            [
                'skill' => 'reading', 'type' => 'vocabulary', 'category' => 'vocabulary_in_context',
                'question_text' => 'The company faced ___ criticism after the product recall.',
                'options' => ['widespread', 'narrow', 'quiet', 'private'],
                'correct_answer' => 'widespread',
                'explanation' => '"Widespread" means affecting a large area or many people.',
            ],
            [
                'skill' => 'reading', 'type' => 'vocabulary', 'category' => 'vocabulary_in_context',
                'question_text' => 'She gave a ___ account of the events, leaving nothing out.',
                'options' => ['comprehensive', 'brief', 'vague', 'casual'],
                'correct_answer' => 'comprehensive',
                'explanation' => '"Comprehensive" means including all or nearly all elements.',
            ],
            [
                'skill' => 'reading', 'type' => 'vocabulary', 'category' => 'vocabulary_in_context',
                'question_text' => 'The two sides reached a ___ agreement after long negotiations.',
                'options' => ['mutual', 'one-sided', 'temporary', 'verbal'],
                'correct_answer' => 'mutual',
                'explanation' => '"Mutual" means shared by both or all parties involved.',
            ],
            [
                'skill' => 'reading', 'type' => 'vocabulary', 'category' => 'vocabulary_in_context',
                'question_text' => 'Her ___ approach to management earned her the team\'s trust.',
                'options' => ['transparent', 'secretive', 'rigid', 'passive'],
                'correct_answer' => 'transparent',
                'explanation' => '"Transparent" means open, honest, and easy to understand.',
            ],
            [
                'skill' => 'reading', 'type' => 'vocabulary', 'category' => 'vocabulary_in_context',
                'question_text' => 'The charity relies ___ on donations from the public to operate.',
                'options' => ['heavily', 'lightly', 'rarely', 'briefly'],
                'correct_answer' => 'heavily',
                'explanation' => '"Rely heavily on" means to depend greatly on something.',
            ],
            [
                'skill' => 'reading', 'type' => 'vocabulary', 'category' => 'vocabulary_in_context',
                'question_text' => 'The government introduced ___ measures to tackle the economic crisis.',
                'options' => ['drastic', 'minimal', 'traditional', 'gentle'],
                'correct_answer' => 'drastic',
                'explanation' => '"Drastic" means severe and far-reaching in effect.',
            ],
            [
                'skill' => 'reading', 'type' => 'vocabulary', 'category' => 'vocabulary_in_context',
                'question_text' => 'The research findings were ___, contradicting all previous studies.',
                'options' => ['controversial', 'expected', 'dull', 'ordinary'],
                'correct_answer' => 'controversial',
                'explanation' => '"Controversial" means causing disagreement or debate.',
            ],
            [
                'skill' => 'reading', 'type' => 'vocabulary', 'category' => 'vocabulary_in_context',
                'question_text' => 'The company\'s profits have ___ significantly over the past three years.',
                'options' => ['declined', 'expanded', 'paused', 'maintained'],
                'correct_answer' => 'declined',
                'explanation' => '"Declined" means decreased or became smaller in amount.',
            ],
        ];
    }

    private function listeningDialogues(): array
    {
        $dialogues = [
            [
                'context' => 'A conversation between two colleagues about an upcoming project deadline',
                'script' => 'A: Have you finished the market analysis yet? B: Not quite. I\'ve done the quantitative part but I\'m still working on the conclusions. A: The deadline is Thursday morning, remember. B: I know. I\'ll have everything ready by Wednesday evening at the latest. A: Good. The director wants to review it before the board meeting.',
                'questions' => [
                    ['q' => 'What part of the market analysis has B completed?', 'a' => 'the quantitative part', 'opts' => ['the quantitative part', 'the conclusions', 'the introduction', 'nothing yet']],
                    ['q' => 'When will B have everything ready?', 'a' => 'Wednesday evening', 'opts' => ['Wednesday evening', 'Thursday morning', 'Tuesday afternoon', 'Friday']],
                ],
            ],
            [
                'context' => 'A phone conversation between a customer and a bank representative',
                'script' => 'C: Hello, I\'d like to report a suspicious transaction on my account. R: Of course, can I take your account number please? C: Yes, it\'s 4521-8823. R: Thank you. Can you tell me more about the transaction? C: There\'s a charge of £350 from a company I don\'t recognise from last Tuesday. R: I can see that. We\'ll place a temporary block on your card and investigate.',
                'questions' => [
                    ['q' => 'Why is the customer calling?', 'a' => 'to report a suspicious transaction', 'opts' => ['to report a suspicious transaction', 'to close their account', 'to request a new card', 'to check their balance']],
                    ['q' => 'What will the bank do immediately?', 'a' => 'place a temporary block on the card', 'opts' => ['place a temporary block on the card', 'refund the money', 'contact the police', 'send a new card']],
                ],
            ],
            [
                'context' => 'A conversation at a job interview',
                'script' => 'I: Tell me about your greatest professional achievement. C: I led a team of eight people to deliver a major software project three weeks ahead of schedule. I: How did you manage that? C: Through clear communication, daily stand-ups, and by removing obstacles quickly. I: And what would you say was the biggest challenge? C: Keeping morale high during the most stressful phases.',
                'questions' => [
                    ['q' => 'How many people did the candidate manage?', 'a' => 'eight', 'opts' => ['eight', 'five', 'twelve', 'three']],
                    ['q' => 'What was the biggest challenge the candidate mentions?', 'a' => 'keeping morale high during stressful phases', 'opts' => ['keeping morale high during stressful phases', 'meeting the deadline', 'managing the budget', 'technical problems']],
                ],
            ],
            [
                'context' => 'Two friends discussing plans for the weekend',
                'script' => 'A: Are you coming to Sarah\'s party on Saturday? B: I\'d love to but I\'ve got a commitment. I promised to help my sister move house. A: Oh right, I forgot she was moving. B: Yeah, we\'re doing it on Saturday morning. If we finish early enough I might pop in for a bit in the evening. A: The party starts at seven, so there\'s plenty of time.',
                'questions' => [
                    ['q' => 'Why can\'t B definitely come to the party?', 'a' => 'they are helping their sister move house', 'opts' => ['they are helping their sister move house', 'they are working', 'they are not invited', 'they are unwell']],
                    ['q' => 'What time does the party start?', 'a' => 'seven o\'clock', 'opts' => ['seven o\'clock', 'eight o\'clock', 'six o\'clock', 'nine o\'clock']],
                ],
            ],
            [
                'context' => 'A discussion between a doctor and a patient',
                'script' => 'D: Your blood pressure is a little high. Have you been under much stress lately? P: Quite a bit, yes. My workload has been very heavy for the past two months. D: That could be a contributing factor. I\'d recommend reducing your salt intake and trying to get at least thirty minutes of exercise daily. P: Should I take medication? D: Not at this stage. Let\'s review in six weeks and see if lifestyle changes are enough.',
                'questions' => [
                    ['q' => 'What does the doctor recommend the patient do?', 'a' => 'reduce salt intake and exercise daily', 'opts' => ['reduce salt intake and exercise daily', 'take medication immediately', 'reduce working hours', 'have surgery']],
                    ['q' => 'When will the doctor review the patient\'s condition?', 'a' => 'in six weeks', 'opts' => ['in six weeks', 'in three months', 'next week', 'in a year']],
                ],
            ],
            [
                'context' => 'A travel agent helping a customer plan a holiday',
                'script' => 'A: I\'m looking for something warm in October, ideally with good beaches but not too crowded. T: Have you considered the Canary Islands? They have excellent weather in October and are much quieter than in peak summer. A: How long would a typical holiday be? T: Most people go for one to two weeks. A seven-night stay including flights usually costs around £800 per person. A: That sounds very reasonable.',
                'questions' => [
                    ['q' => 'Why does the agent recommend the Canary Islands for October?', 'a' => 'good weather and less crowded', 'opts' => ['good weather and less crowded', 'cheapest option available', 'easiest to reach', 'only warm destination']],
                    ['q' => 'How much does a seven-night stay typically cost per person?', 'a' => '£800', 'opts' => ['£800', '£600', '£1000', '£1200']],
                ],
            ],
            [
                'context' => 'Two students discussing university options',
                'script' => 'A: Have you decided where you\'re applying yet? B: I\'ve narrowed it down to three universities. My first choice is Edinburgh. A: Edinburgh? That\'s quite far from home. B: I know, but they have the best programme for marine biology in the country. A: Have you visited the campus? B: Yes, I went on an open day last month. The facilities were incredible.',
                'questions' => [
                    ['q' => 'Why does B want to study in Edinburgh?', 'a' => 'it has the best marine biology programme', 'opts' => ['it has the best marine biology programme', 'it is close to home', 'it is the cheapest option', 'a friend recommended it']],
                    ['q' => 'When did B visit the campus?', 'a' => 'last month', 'opts' => ['last month', 'last year', 'last week', 'two months ago']],
                ],
            ],
            [
                'context' => 'A manager giving feedback to an employee',
                'script' => 'M: I wanted to speak with you about your performance over the last quarter. Overall, I\'m very pleased. Your sales figures were up 18% year on year. E: Thank you, I worked really hard on the client relationships. M: It shows. One area I\'d like you to work on is your written reports. They sometimes lack detail. E: I understand. I\'ll pay more attention to that going forward. M: Good. I\'m recommending you for the senior sales role.',
                'questions' => [
                    ['q' => 'By how much did the employee\'s sales figures increase?', 'a' => '18%', 'opts' => ['18%', '12%', '25%', '8%']],
                    ['q' => 'What does the manager want the employee to improve?', 'a' => 'the detail in written reports', 'opts' => ['the detail in written reports', 'punctuality', 'client relationships', 'sales strategy']],
                ],
            ],
            [
                'context' => 'A conversation about renting a flat',
                'script' => 'L: The flat has two bedrooms, a modern kitchen, and parking. T: Is the monthly rent negotiable? L: Not really. It\'s £1,200 and that includes water rates. T: What about internet? L: That\'s not included but the previous tenant was with a provider that charges about £30 a month. T: And when is the earliest I could move in? L: The flat will be available from the first of next month.',
                'questions' => [
                    ['q' => 'What is included in the monthly rent?', 'a' => 'water rates', 'opts' => ['water rates', 'internet', 'electricity', 'council tax']],
                    ['q' => 'When can the tenant move in?', 'a' => 'the first of next month', 'opts' => ['the first of next month', 'immediately', 'in two months', 'at the end of the month']],
                ],
            ],
            [
                'context' => 'Two colleagues talking about a training course',
                'script' => 'A: Are you going to the digital marketing training next week? B: I wasn\'t planning to. Is it compulsory? A: No, but the HR manager strongly recommends it for anyone in our department. B: How long does it last? A: Two days. Wednesday and Thursday. B: I\'ve got a client meeting on Thursday morning. A: The course finishes at lunchtime on Thursday, so you should be fine.',
                'questions' => [
                    ['q' => 'Is the training course compulsory?', 'a' => 'no, but strongly recommended', 'opts' => ['no, but strongly recommended', 'yes, for all staff', 'only for managers', 'optional for all departments']],
                    ['q' => 'When does the course finish on Thursday?', 'a' => 'at lunchtime', 'opts' => ['at lunchtime', 'in the morning', 'in the evening', 'at three o\'clock']],
                ],
            ],
            [
                'context' => 'A conversation at a supermarket checkout',
                'script' => 'C: Do you have a loyalty card? S: Yes, here it is. C: Thank you. Your total today is £47.35. S: Can I pay by contactless? C: Of course. The maximum for contactless is £100, so that\'s fine. S: Great. Oh, do you have bags? C: Yes, they\'re 10p each. S: I\'ll take two please.',
                'questions' => [
                    ['q' => 'What is the customer\'s total?', 'a' => '£47.35', 'opts' => ['£47.35', '£43.75', '£52.10', '£39.50']],
                    ['q' => 'How much does each bag cost?', 'a' => '10p', 'opts' => ['10p', '5p', '20p', '15p']],
                ],
            ],
            [
                'context' => 'Two friends talking after watching a film',
                'script' => 'A: What did you think of the ending? B: Honestly, I found it a bit confusing. A: Me too at first, but then I read that the director intentionally left it open to interpretation. B: Oh, that explains a lot. The cinematography was stunning though. A: Absolutely. And the soundtrack was incredible. B: It\'s definitely one of the best films I\'ve seen this year.',
                'questions' => [
                    ['q' => 'Why was the ending intentionally confusing?', 'a' => 'the director left it open to interpretation', 'opts' => ['the director left it open to interpretation', 'the budget ran out', 'the script was changed last minute', 'the actors improvised']],
                    ['q' => 'What does B particularly praise about the film?', 'a' => 'the cinematography', 'opts' => ['the cinematography', 'the acting', 'the story', 'the special effects']],
                ],
            ],
        ];

        $exercises = [];
        foreach ($dialogues as $d) {
            foreach ($d['questions'] as $q) {
                $exercises[] = [
                    'skill' => 'listening',
                    'type' => 'listening_comprehension',
                    'category' => 'short_dialogue',
                    'question_text' => "[Context: {$d['context']}]\n\nTranscript: {$d['script']}\n\nQuestion: {$q['q']}",
                    'options' => $q['opts'],
                    'correct_answer' => $q['a'],
                    'explanation' => 'Based on the information given in the dialogue.',
                ];
            }
        }

        return $exercises;
    }

    private function listeningMonologues(): array
    {
        $monologues = [
            [
                'context' => 'A radio announcement about road closures',
                'script' => 'Drivers are advised that the A34 northbound will be closed between Junction 7 and Junction 9 from Monday the 15th until Friday the 19th for essential resurfacing work. Traffic will be diverted via the B2056. Motorists should allow an extra twenty minutes for their journeys. The work is being carried out by the county council and is expected to be completed by 6am on Friday morning.',
                'questions' => [
                    ['q' => 'Which road is being closed?', 'a' => 'the A34 northbound', 'opts' => ['the A34 northbound', 'the B2056', 'the A34 southbound', 'the M25']],
                    ['q' => 'How much extra time should drivers allow?', 'a' => 'twenty minutes', 'opts' => ['twenty minutes', 'thirty minutes', 'ten minutes', 'an hour']],
                ],
            ],
            [
                'context' => 'A lecture about the history of the internet',
                'script' => 'The internet as we know it today evolved from a network called ARPANET, developed in the late 1960s by the United States Department of Defense. Initially, it connected only four universities. By the early 1990s, Tim Berners-Lee had developed the World Wide Web, making the internet accessible to the general public. Today, over five billion people use the internet, and it has transformed nearly every aspect of human life, from communication to commerce and education.',
                'questions' => [
                    ['q' => 'How many universities were connected by ARPANET initially?', 'a' => 'four', 'opts' => ['four', 'ten', 'two', 'twenty']],
                    ['q' => 'Who developed the World Wide Web?', 'a' => 'Tim Berners-Lee', 'opts' => ['Tim Berners-Lee', 'Bill Gates', 'Steve Jobs', 'Mark Zuckerberg']],
                ],
            ],
            [
                'context' => 'A tour guide introducing a museum exhibit',
                'script' => 'Welcome to the Ancient Egypt gallery. This collection includes over three hundred artefacts spanning four thousand years of history. You\'ll find everything from everyday household items used by ordinary Egyptians to elaborate burial treasures belonging to pharaohs. Please note that photography is permitted throughout the gallery, but flash photography must be switched off to protect the delicate pigments. Audio guides are available at the main entrance for £3.50.',
                'questions' => [
                    ['q' => 'What photography restriction is mentioned?', 'a' => 'flash photography must be switched off', 'opts' => ['flash photography must be switched off', 'no photography at all', 'only photographs of certain items', 'cameras must be left at the entrance']],
                    ['q' => 'How much does an audio guide cost?', 'a' => '£3.50', 'opts' => ['£3.50', '£2.50', '£5.00', 'free']],
                ],
            ],
            [
                'context' => 'A voicemail message from an employer',
                'script' => 'Hello, this is a message for James Clarke from HR at Meridian Group. I\'m calling regarding your application for the project coordinator role. We\'d like to invite you to a first-stage interview next Thursday at 2pm at our offices in Bristol. Could you please confirm your availability by calling back on 0117 456 7890 or emailing recruitment at meridiangroup dot com? We look forward to hearing from you.',
                'questions' => [
                    ['q' => 'What role has James applied for?', 'a' => 'project coordinator', 'opts' => ['project coordinator', 'marketing manager', 'HR assistant', 'financial analyst']],
                    ['q' => 'When is the interview scheduled?', 'a' => 'next Thursday at 2pm', 'opts' => ['next Thursday at 2pm', 'next Monday at 10am', 'this Friday at 3pm', 'next Wednesday at 11am']],
                ],
            ],
            [
                'context' => 'A news report about climate action',
                'script' => 'The government has announced a new package of measures to reduce carbon emissions by 45% by 2030. The plan includes a ban on new petrol and diesel car sales from 2028, subsidies for home insulation, and the expansion of offshore wind farms. The environment secretary described the announcement as a turning point in the country\'s approach to climate change. Environmental groups have welcomed the measures but say further action will be needed to meet long-term net zero targets.',
                'questions' => [
                    ['q' => 'What is the government\'s carbon emission reduction target?', 'a' => '45% by 2030', 'opts' => ['45% by 2030', '30% by 2025', '60% by 2035', '50% by 2028']],
                    ['q' => 'From what year will new petrol and diesel car sales be banned?', 'a' => '2028', 'opts' => ['2028', '2025', '2030', '2035']],
                ],
            ],
            [
                'context' => 'A presentation about healthy eating',
                'script' => 'A balanced diet is one of the most important factors in maintaining long-term health. Nutritionists recommend eating at least five portions of fruit and vegetables per day, limiting processed foods, and ensuring adequate protein intake from sources such as fish, pulses, and lean meat. Hydration is also crucial — adults should aim to drink around two litres of water daily. Small, regular meals throughout the day are preferable to large, infrequent ones for maintaining stable energy levels.',
                'questions' => [
                    ['q' => 'How many portions of fruit and vegetables are recommended daily?', 'a' => 'five', 'opts' => ['five', 'three', 'seven', 'two']],
                    ['q' => 'How much water should adults drink daily?', 'a' => 'around two litres', 'opts' => ['around two litres', 'one litre', 'three litres', 'half a litre']],
                ],
            ],
            [
                'context' => 'An announcement about library services',
                'script' => 'The Central Library will be closed for refurbishment from the 3rd to the 24th of April. During this time, borrowers can use the mobile library service, which operates on Tuesdays and Thursdays from 10am to 4pm at designated stops throughout the town. All existing loans have been automatically extended until the 30th of April. Digital services, including e-books and online databases, will remain fully available throughout the closure period.',
                'questions' => [
                    ['q' => 'How long will the library be closed?', 'a' => 'from the 3rd to the 24th of April', 'opts' => ['from the 3rd to the 24th of April', 'for one week', 'for the whole of April', 'from the 1st to the 15th of April']],
                    ['q' => 'When does the mobile library operate?', 'a' => 'Tuesdays and Thursdays', 'opts' => ['Tuesdays and Thursdays', 'Monday to Friday', 'weekends only', 'every day except Sunday']],
                ],
            ],
        ];

        $exercises = [];
        foreach ($monologues as $m) {
            foreach ($m['questions'] as $q) {
                $exercises[] = [
                    'skill' => 'listening',
                    'type' => 'listening_comprehension',
                    'category' => 'monologue',
                    'question_text' => "[Context: {$m['context']}]\n\nTranscript: {$m['script']}\n\nQuestion: {$q['q']}",
                    'options' => $q['opts'],
                    'correct_answer' => $q['a'],
                    'explanation' => 'Based on the information provided in the audio transcript.',
                ];
            }
        }

        return $exercises;
    }

    private function writingExercises(): array
    {
        return [
            // Informal email (5)
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'informal_email',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'You have received a message from your English-speaking friend, Alex, who says: "I heard you recently started a new job. How is it going? What do you enjoy most about it? Has anything been challenging?" Write an email to Alex answering all three questions. Write between 140 and 190 words.',
                'explanation' => 'Use informal language, contractions, and friendly expressions. Organise your answer clearly with opening, body, and closing.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'informal_email',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Your friend Sam has invited you to a party next weekend, but you cannot attend. Write an email to Sam explaining why you cannot come, apologising, and suggesting an alternative time to meet. Write between 140 and 190 words.',
                'explanation' => 'Use informal phrases for apologising and making suggestions. Include all points from the task.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'informal_email',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'An English-speaking friend is planning to visit your city for the first time. Write an email recommending three things they should see or do, explaining why each recommendation is worth visiting. Write between 140 and 190 words.',
                'explanation' => 'Use informal language and enthusiastic expressions. Give clear reasons for your recommendations.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'informal_email',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Your friend has asked for your advice about whether to study abroad for a year. Write an email giving your opinion, discussing the advantages and potential challenges, and making a clear recommendation. Write between 140 and 190 words.',
                'explanation' => 'Structure your advice clearly. Use language for giving opinions and recommendations in an informal style.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'informal_email',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'You recently read a book your friend recommended, but you did not enjoy it. Write an email to your friend saying what you found disappointing, while being tactful and asking for another recommendation. Write between 140 and 190 words.',
                'explanation' => 'Use tactful, polite language while expressing your honest opinion. Ask for further recommendations diplomatically.',
            ],
            // Formal letter (5)
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'formal_letter',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'You bought a laptop online three weeks ago. It stopped working after one week. Write a formal letter of complaint to the company, explaining the problem, what action you have already taken, and what resolution you expect. Write between 140 and 190 words.',
                'explanation' => 'Use formal language throughout. Begin with "Dear Sir/Madam" and end with "Yours faithfully". Include all required information.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'formal_letter',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'You saw an advertisement for a summer internship at an international marketing company. Write a formal letter of application, explaining why you are interested in the position and what relevant skills and experience you have. Write between 140 and 190 words.',
                'explanation' => 'Use professional language and a persuasive tone. Structure with an introduction, main body covering skills and experience, and a conclusion.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'formal_letter',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'You have noticed that the sports facilities in your local area have become run down. Write a formal letter to the local council suggesting improvements, explaining why they are necessary and what benefits they would bring to the community. Write between 140 and 190 words.',
                'explanation' => 'Use formal language and a polite but persuasive tone. Clearly outline the problem and your proposed solutions.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'formal_letter',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'A local newspaper published an article claiming that young people today are less healthy than previous generations. Write a formal letter to the editor responding to this claim, providing evidence to support or challenge it. Write between 140 and 190 words.',
                'explanation' => 'Use formal letter conventions. Present a clear argument supported by examples or evidence.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'formal_letter',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'You would like to volunteer at a local environmental charity. Write a formal letter to the charity\'s director expressing your interest, describing your relevant experience and explaining what you hope to contribute. Write between 140 and 190 words.',
                'explanation' => 'Use formal language and a positive, enthusiastic tone. Include all relevant personal information and motivations.',
            ],
            // Essay (5)
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'essay',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Some people believe that social media has a more negative than positive impact on society. Write an essay discussing both sides of this argument and giving your own conclusion. Write between 140 and 190 words.',
                'explanation' => 'Present arguments on both sides before giving a balanced conclusion. Use appropriate discourse markers and essay language.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'essay',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'In your English class, you have been discussing whether it is better to live in a big city or in the countryside. Write an essay comparing the two lifestyles and stating which you think is preferable and why. Write between 140 and 190 words.',
                'explanation' => 'Compare and contrast both lifestyles. Give your personal preference with supporting reasons.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'essay',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Your teacher has asked you to write an essay on the following topic: "Technology has made our lives better in every way." Do you agree or disagree? Write an essay giving your opinion with relevant examples. Write between 140 and 190 words.',
                'explanation' => 'Take a clear position and support it with examples. Use formal essay language and a clear paragraph structure.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'essay',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Discuss the advantages and disadvantages of gap years — taking a year off between school and university. Write an essay presenting both sides and giving your own view on whether gap years are worthwhile. Write between 140 and 190 words.',
                'explanation' => 'Present balanced arguments for and against. Conclude with a clear personal opinion.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'essay',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Some people argue that university education should be free for all students. Write an essay discussing both views on this issue and expressing your own opinion. Write between 140 and 190 words.',
                'explanation' => 'Discuss the economic, social, and educational arguments on both sides before giving a well-supported conclusion.',
            ],
            // Report/Review (5)
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'report_review',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Your school principal has asked you to write a report on whether the school should introduce a uniform policy. Include sections on arguments for and against, and make a recommendation. Write between 140 and 190 words.',
                'explanation' => 'Use report format with headed sections. Use formal language and make a clear, justified recommendation.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'report_review',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'You recently attended an international food festival in your city. Write a review for a travel website describing the event, what you enjoyed, what could be improved, and whether you would recommend it. Write between 140 and 190 words.',
                'explanation' => 'Write in a lively, engaging style. Include both positive and negative aspects and give a clear recommendation.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'report_review',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'A local English-language magazine has asked readers to submit a review of a film they have seen recently. Write a review of a film you have watched, covering the plot, acting, and whether you recommend it. Write between 140 and 190 words.',
                'explanation' => 'Write in an engaging style with a clear recommendation. Avoid revealing the complete plot ending.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'report_review',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'The manager of the sports centre where you work has asked for a report on how to attract more young members. Describe the current situation, identify problems, and make specific recommendations. Write between 140 and 190 words.',
                'explanation' => 'Use formal report language with clear sections. Be specific with your recommendations and justify them.',
            ],
            [
                'skill' => 'writing', 'type' => 'open_ended', 'category' => 'report_review',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'You stayed at a hotel during a recent trip. Write a review for a travel website, commenting on the location, facilities, and service. Include what impressed you and what could be improved, and give an overall rating. Write between 140 and 190 words.',
                'explanation' => 'Write in an informative yet engaging style. Cover all aspects asked for and give a balanced, honest review.',
            ],
        ];
    }

    private function speakingExercises(): array
    {
        return [
            // Personal description (8)
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'personal_description',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Talk about a person who has had a significant influence on your life. Describe who they are, how you know them, why they have been important to you, and what you have learned from them. Speak for 1-2 minutes.',
                'explanation' => 'Use descriptive language, past and present tenses, and expressions for explaining influence and importance.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'personal_description',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Describe your hometown or the place where you grew up. Talk about what it is like, what you enjoy about living there, and whether you would recommend it to visitors. Speak for 1-2 minutes.',
                'explanation' => 'Use descriptive vocabulary for places, present tense for general truths, and expressions of recommendation.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'personal_description',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Tell me about a memorable journey or trip you have taken. Where did you go, who were you with, what happened, and why do you remember it? Speak for 1-2 minutes.',
                'explanation' => 'Use past tenses to narrate events, descriptive language, and expressions for emphasising memorable details.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'personal_description',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Talk about a hobby or interest you have. Describe what it involves, when you started doing it, why you enjoy it, and how much time you spend on it. Speak for 1-2 minutes.',
                'explanation' => 'Use vocabulary related to hobbies and free time. Include present tense for habits and past tense for how you started.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'personal_description',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Describe a time when you faced a difficult challenge and how you dealt with it. What was the situation, what did you do, and what did you learn from the experience? Speak for 1-2 minutes.',
                'explanation' => 'Use past tenses, narrative language, and expressions for explaining consequences and learning outcomes.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'personal_description',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Talk about your ideal job or career. What would you like to do, what skills does it require, and what steps have you taken or plan to take to achieve it? Speak for 1-2 minutes.',
                'explanation' => 'Use future tenses and conditionals to discuss plans and aspirations. Use vocabulary related to careers and skills.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'personal_description',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Describe a book, film, or TV series that you have enjoyed recently. What is it about, what do you like about it, and would you recommend it to a friend? Speak for 1-2 minutes.',
                'explanation' => 'Use present and past tenses. Include vocabulary for narrating plot and expressing personal reactions and recommendations.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'personal_description',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Talk about an important decision you made in the past. Describe the situation, the options you considered, the decision you made, and whether you think it was the right one. Speak for 1-2 minutes.',
                'explanation' => 'Use past tenses and conditional structures. Include language for weighing options and reflecting on consequences.',
            ],
            // Opinion (7)
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'opinion',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Do you think social media is mostly beneficial or harmful for young people? Give your opinion with reasons and examples. Speak for 1-2 minutes.',
                'explanation' => 'Use opinion language, discourse markers, and examples to support your view. Acknowledge the other side of the argument briefly.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'opinion',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Some people believe that everyone should be required to learn a foreign language at school. What is your opinion? Give reasons and examples to support your view. Speak for 1-2 minutes.',
                'explanation' => 'State your opinion clearly, use reasons and examples, and use discourse markers to structure your argument.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'opinion',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Do you think working from home is better than working in an office? Express your opinion with specific reasons and examples. Speak for 1-2 minutes.',
                'explanation' => 'Use comparative language and opinion phrases. Support each point with a reason or example.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'opinion',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'In your opinion, what is the most important environmental problem facing the world today? Explain why you think so and suggest one or two possible solutions. Speak for 1-2 minutes.',
                'explanation' => 'Express a clear opinion, justify your choice, and use language for making suggestions and proposals.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'opinion',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Do you think that university education is essential for success in life? Give your opinion with supporting reasons and examples. Speak for 1-2 minutes.',
                'explanation' => 'Present a clear, supported argument. Acknowledge contrasting views and use appropriate hedging language.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'opinion',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Some people say that sport is more important than art in schools. What do you think? Give reasons for your opinion and consider the opposite view. Speak for 1-2 minutes.',
                'explanation' => 'Use comparative language and opinion phrases. Consider both perspectives before giving your view.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'opinion',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Do you believe that technology has made people less creative? Support your opinion with relevant examples and consider alternative viewpoints. Speak for 1-2 minutes.',
                'explanation' => 'Defend your opinion with examples. Use concession language when addressing opposing views.',
            ],
            // Comparison (5)
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'comparison',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Compare living in a city with living in a small town or village. Discuss the advantages and disadvantages of each and say which you would prefer and why. Speak for 1-2 minutes.',
                'explanation' => 'Use comparative structures (more..., less..., whereas, while, on the other hand). Give balanced coverage of both options.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'comparison',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Compare studying online with studying at a university campus. What are the main similarities and differences? Which do you think is better for most students? Speak for 1-2 minutes.',
                'explanation' => 'Use language for comparing and contrasting. Conclude with a supported recommendation.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'comparison',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Compare traditional paper books with e-books and audiobooks. What are the advantages and disadvantages of each format? Which do you personally prefer? Speak for 1-2 minutes.',
                'explanation' => 'Use comparative language and vocabulary for talking about media and reading. Include personal preference with reasons.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'comparison',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Compare public transport with using a private car for daily travel. Consider factors such as cost, environmental impact, convenience, and safety. Which would you recommend? Speak for 1-2 minutes.',
                'explanation' => 'Compare across multiple dimensions using appropriate language. Make a clear recommendation with justification.',
            ],
            [
                'skill' => 'speaking', 'type' => 'open_ended', 'category' => 'comparison',
                'question_text' => null,
                'options' => null,
                'correct_answer' => '',
                'user_input_instruction' => 'Compare working for a large corporation with working for a small startup company. What are the key differences in terms of career development, work culture, and job security? Which would you prefer? Speak for 1-2 minutes.',
                'explanation' => 'Use vocabulary for the workplace and careers. Compare on several dimensions and conclude with a personal preference.',
            ],
        ];
    }
}
