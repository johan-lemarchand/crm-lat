import { Loader2 } from 'lucide-react';

interface ProcessStep {
  id: number;
  name: string;
  progress: number;
}

interface VerificationProgressProps {
    progress: number;
    isLoading: boolean;
    currentStep: number;
    steps: ProcessStep[];
    isPaused?: boolean;
    hasError?: boolean;
    onProgressChange?: (newProgress: number) => void;
}

export function VerificationProgress({ 
    progress, 
    isLoading, 
    currentStep,
    steps,
}: VerificationProgressProps) {

    return (
        <div className="space-y-4">
            <div className="relative pt-1">
                <div className="flex mb-2 items-center justify-between">
                    <div>
                        <span className="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-blue-600 bg-blue-200">
                            Progression
                        </span>
                    </div>
                    <div className="text-right">
                        <span className="text-xs font-semibold inline-block text-blue-600">
                            {progress}%
                        </span>
                    </div>
                </div>
                <div className="overflow-hidden h-2 mb-4 text-xs flex rounded bg-blue-200">
                    <div 
                        style={{ width: `${progress}%` }} 
                        className={`shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-500 ${isLoading ? 'animate-pulse' : ''}`}
                    ></div>
                </div>
            </div>
            
            <div className="flex justify-between relative">
                <div className="absolute top-5 left-[25px] right-[25px] h-0.5 bg-gray-300"></div>
                
                <div 
                    className="absolute top-5 left-[25px] h-0.5 bg-green-500 transition-all duration-500"
                    style={{ 
                        width: `calc((100% - 50px) * ${progress / 100})`
                    }}
                ></div>
                
                {steps.map((step) => {
                    const status = step.id < currentStep ? 'completed' :
                                   step.id === currentStep ? (isLoading ? 'current' : 'completed') : 
                                   'pending';
                    
                    return (
                        <div 
                            key={step.id} 
                            className="flex flex-col items-center z-10"
                        >
                            <div 
                                className={`
                                    w-10 h-10 rounded-full flex items-center justify-center text-white font-medium
                                    ${status === 'completed' ? 'bg-green-500' : status === 'current' ? 'bg-blue-500' : 'bg-gray-300'}
                                    ${isLoading && step.id === currentStep ? 'animate-pulse' : ''}
                                `}
                            >
                                {isLoading && step.id === currentStep ? (
                                    <Loader2 className="h-5 w-5 animate-spin" />
                                ) : (
                                    step.id
                                )}
                            </div>
                            <span className="text-xs text-center mt-1 max-w-[80px]">{step.name}</span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
} 