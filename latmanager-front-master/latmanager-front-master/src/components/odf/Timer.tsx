import { useEffect, useState, useRef } from 'react';
import { Clock, Pause } from 'lucide-react';

interface TimerProps {
    isPaused: boolean;
    shouldReset?: boolean;
    isActive: boolean;
    elapsedTime: number;
    pauseTime: number;
    onElapsedTimeChange: (time: number) => void;
    onPauseTimeChange: (time: number) => void;
    onTimeExceeded?: () => void;
}

export function Timer({ 
    isPaused, 
    shouldReset, 
    isActive,
    elapsedTime,
    pauseTime,
    onElapsedTimeChange,
    onPauseTimeChange,
    onTimeExceeded
}: TimerProps) {
    const startTimeRef = useRef(Date.now());
    const pauseStartTimeRef = useRef(0);
    const lastElapsedRef = useRef(0);
    const [showPauseTimer, setShowPauseTimer] = useState(false);
    const TIME_LIMIT = 720; // 12 minutes en secondes
    
    // Gestion du timer principal
    useEffect(() => {
        let interval: NodeJS.Timeout;
        
        if (!isPaused && !isActive) {
            interval = setInterval(() => {
                onElapsedTimeChange(prev => prev + 1);
            }, 1000);
        } else if (isActive) {
            interval = setInterval(() => {
                onPauseTimeChange(prev => {
                    const newTime = prev + 1;
                    if (newTime >= 12 * 60) { // 12 minutes
                        clearInterval(interval);
                        onTimeExceeded();
                    }
                    return newTime;
                });
            }, 1000);
        }
        
        return () => clearInterval(interval);
    }, [isPaused, isActive, onElapsedTimeChange, onPauseTimeChange, onTimeExceeded]);
    
    // Ne réinitialiser les timers que lorsque shouldReset change explicitement
    useEffect(() => {
        if (shouldReset) {
            onElapsedTimeChange(0);
            onPauseTimeChange(0);
        }
    }, [shouldReset, onElapsedTimeChange, onPauseTimeChange]);
    
    // Vérifier si on dépasse la limite de temps uniquement pour l'étape 1
    useEffect(() => {
        // On ne vérifie le dépassement que si on est en pause active (étape 1)
        if (isActive && isPaused && pauseTime >= TIME_LIMIT && onTimeExceeded) {
            onTimeExceeded();
        }
    }, [pauseTime, onTimeExceeded, isActive, isPaused]);
    
    // Gestion du timer de pause
    useEffect(() => {
        let pauseIntervalId: NodeJS.Timeout;
        
        if (isPaused && isActive) {
            setShowPauseTimer(true);
            
            pauseIntervalId = setInterval(() => {
                onPauseTimeChange(pauseTime + 1);
            }, 1000);
        }
        
        return () => {
            if (pauseIntervalId) clearInterval(pauseIntervalId);
        };
    }, [isPaused, isActive, pauseTime]);
    
    const formatTime = (t: number) => {
        const minutes = Math.floor(t / 60);
        const seconds = t % 60;
        return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    };
    
    return (
        <div className="flex items-center gap-4">
            <div className="flex items-center gap-2">
                <Clock className="h-4 w-4 text-purple-600" />
                <span className={`font-mono text-lg ${isActive && pauseTime >= TIME_LIMIT ? 'text-red-600' : 'text-purple-600'}`}>
                    {formatTime(elapsedTime)}
                </span>
            </div>
            
            {showPauseTimer && (
                <div className="flex items-center gap-2">
                    <Pause className="h-4 w-4 text-orange-600" />
                    <span className={`font-mono text-lg ${isActive && pauseTime >= TIME_LIMIT ? 'text-red-600' : 'text-orange-600'}`}>
                        {formatTime(pauseTime)}
                    </span>
                </div>
            )}
        </div>
    );
} 