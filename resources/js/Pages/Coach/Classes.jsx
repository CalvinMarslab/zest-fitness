import AppLayout from '@/Layouts/AppLayout';

export default function Classes({ classes }) {
    return (
        <AppLayout>
            <div className="max-w-4xl mx-auto py-8 px-4">
                <h1 className="text-2xl font-bold mb-6">My Classes</h1>
                <div className="space-y-4">
                    {classes.map((cls) => (
                        <div key={cls.id} className="bg-white rounded-lg shadow p-4">
                            <div className="font-semibold">{cls.name}</div>
                            <div className="text-sm text-gray-500">{cls.coach}</div>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
