import {
    Body,
    Container,
    Head,
    Heading,
    Html,
    Preview,
    Text,
    Tailwind,
    render
} from '@react-email/components';

interface CommandExecutionEmailProps {
    commandName: string;
    scriptName: string;
    status: 'success' | 'error' | 'warning';
    output?: string;
    errorOutput?: string;
    executionDate: string;
}

const CommandExecutionEmail = ({
    commandName,
    scriptName,
    status,
    executionDate
}: CommandExecutionEmailProps) => {
    const statusColors = {
        success: 'text-green-500',
        error: 'text-red-500',
        warning: 'text-yellow-500'
    };

    const statusText = {
        success: 'Succès',
        error: 'Erreur',
        warning: 'Attention'
    };

    return (
        <Html>
            <Head />
            <Preview>Résultat de l'exécution de la commande {commandName}</Preview>
            <Tailwind>
                <Body className="bg-white my-12 mx-auto font-sans">
                    <Container className="border border-solid border-[#eaeaea] rounded my-[40px] mx-auto p-[20px] max-w-[465px]">
                        <Heading className="text-black text-[24px] font-normal text-center p-0 my-[30px] mx-0">
                            Exécution de la commande
                        </Heading>
                        <Text className="text-black text-[14px] leading-[24px]">
                            Commande : <strong>{commandName}:{scriptName}</strong>
                        </Text>
                        <Text className={`text-[14px] leading-[24px] ${statusColors[status]}`}>
                            Statut : <strong>{statusText[status]}</strong>
                        </Text>
                        <Text className="text-black text-[14px] leading-[24px]">
                            Date dernière exécution : {executionDate}
                        </Text>
                    </Container>
                </Body>
            </Tailwind>
        </Html>
    );
};

export const renderCommandExecutionEmail = async (props: CommandExecutionEmailProps): Promise<string> => {
    try {
        const html = await render(<CommandExecutionEmail {...props} />);
        return Array.isArray(html) ? html.join('') : html;
    } catch (error) {
        console.error('Erreur lors du rendu de l\'email:', error);
        // Fallback en cas d'erreur
        return `
            <div>
                <h1>Exécution de la commande</h1>
                <p>Commande : ${props.commandName}:${props.scriptName}</p>
                <p>Statut : ${props.status}</p>
                <p>Date dernière exécution : ${props.executionDate}</p>
            </div>
        `;
    }
}; 