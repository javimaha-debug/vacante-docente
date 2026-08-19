import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import api from '../../lib/api';
import ExamSection from './ExamSection';
import ExamResult from './ExamResult';

const SECTIONS_ORDER = ['reading', 'listening', 'writing'];
const SECTION_INFO = {
    reading: { icon: '📖', label: 'Reading', desc: '10 preguntas de comprensión', minutes: 30 },
    listening: { icon: '🎧', label: 'Listening', desc: '10 preguntas sobre diálogos', minutes: 20 },
    writing: { icon: '✍️', label: 'Writing', desc: '1 tarea de escritura', minutes: 20 },
};

export default function MockExamStart({ onClose }) {
    const [phase, setPhase] = useState('intro'); // intro | exam | result
    const [examData, setExamData] = useState(null);
    const [currentSection, setCurrentSection] = useState(0);
    const [sectionScores, setSectionScores] = useState({});
    const [finalResult, setFinalResult] = useState(null);

    const startMutation = useMutation({
        mutationFn: async () => {
            const res = await api.post('/b2/mock-exam/start');
            return res.data;
        },
        onSuccess: (data) => {
            setExamData(data);
            setPhase('exam');
        },
    });

    const completeMutation = useMutation({
        mutationFn: async (examId) => {
            const res = await api.post(`/b2/mock-exam/${examId}/complete`);
            return res.data;
        },
        onSuccess: (data) => {
            setFinalResult(data);
            setPhase('result');
        },
    });

    const handleSectionComplete = (skill, score) => {
        const updated = { ...sectionScores, [skill]: score };
        setSectionScores(updated);

        if (currentSection < SECTIONS_ORDER.length - 1) {
            setCurrentSection(prev => prev + 1);
        } else {
            // All sections done — complete exam
            completeMutation.mutate(examData.exam_id);
        }
    };

    if (phase === 'intro') {
        return (
            <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                <div className="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 space-y-5 shadow-2xl">
                    <div className="text-center">
                        <div className="text-5xl mb-3">🎓</div>
                        <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">Mock Exam Cambridge B2</h2>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Simulacro oficial adaptado</p>
                    </div>

                    <div className="space-y-3">
                        {SECTIONS_ORDER.map((skill) => (
                            <div key={skill} className="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-xl">
                                <span className="text-2xl">{SECTION_INFO[skill].icon}</span>
                                <div className="flex-1">
                                    <p className="text-sm font-semibold text-gray-800 dark:text-gray-200">{SECTION_INFO[skill].label}</p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">{SECTION_INFO[skill].desc}</p>
                                </div>
                                <span className="text-xs text-gray-400 dark:text-gray-500">{SECTION_INFO[skill].minutes} min</span>
                            </div>
                        ))}
                    </div>

                    <div className="bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 rounded-xl p-3">
                        <p className="text-xs text-amber-700 dark:text-amber-300">
                            ⏱ Duración total: ~70 minutos · Cambridge escala 140-200 · Aprobado: 160 puntos
                        </p>
                    </div>

                    <div className="flex gap-3">
                        <button onClick={onClose} className="flex-1 py-3 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            Cancelar
                        </button>
                        <button
                            onClick={() => startMutation.mutate()}
                            disabled={startMutation.isPending}
                            className="flex-[2] py-3 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-sm font-semibold transition-colors disabled:opacity-60"
                        >
                            {startMutation.isPending ? 'Preparando...' : 'Comenzar examen →'}
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    if (phase === 'exam' && examData) {
        const skill = SECTIONS_ORDER[currentSection];
        const sectionData = examData.sections[skill];
        return (
            <ExamSection
                examId={examData.exam_id}
                skill={skill}
                sectionData={sectionData}
                sectionInfo={SECTION_INFO[skill]}
                sectionIndex={currentSection}
                totalSections={SECTIONS_ORDER.length}
                onComplete={(score) => handleSectionComplete(skill, score)}
            />
        );
    }

    if (phase === 'result' && finalResult) {
        return <ExamResult result={finalResult} onClose={onClose} />;
    }

    return (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
            <div className="bg-white dark:bg-gray-900 rounded-2xl p-8 text-center">
                <div className="animate-spin w-8 h-8 border-4 border-brand-600 border-t-transparent rounded-full mx-auto" />
                <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">Calculando resultados...</p>
            </div>
        </div>
    );
}
