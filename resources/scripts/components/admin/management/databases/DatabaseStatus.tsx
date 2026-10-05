import { useEffect, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheckCircle, faExclamationTriangle } from '@fortawesome/free-solid-svg-icons';
import { httpErrorToHuman } from '@/api/http';
import { DatabaseStatus, getDatabaseStatus } from '@/api/routes/admin/databases';
import Spinner from '@/elements/Spinner';
import Tooltip from '@/elements/tooltip/Tooltip';

export default ({ id }: { id: number }) => {
    const [status, setStatus] = useState<DatabaseStatus | null>(null);

    useEffect(() => {
        getDatabaseStatus(id)
            .then(setStatus)
            .catch(error => setStatus({ online: false, error: httpErrorToHuman(error) }));
    }, [id]);

    if (!status) return <Spinner size={'small'} />;

    return (
        <Tooltip placement={'top'} content={status.online ? 'Connected' : status.error || 'Unable to connect'}>
            <FontAwesomeIcon
                icon={status.online ? faCheckCircle : faExclamationTriangle}
                className={status.online ? 'text-lg text-green-400' : 'text-lg text-red-400'}
            />
        </Tooltip>
    );
};
