<x-layout>
    <style>
        .hero {
            text-align: center;
            padding: 3rem 1rem;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 12px;
            border: 1px solid #334155;
            margin-bottom: 2rem;
        }
        .hero h2 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: #f8fafc;
        }
        .hero p {
            color: #94a3b8;
            font-size: 1.125rem;
            max-width: 600px;
            margin: 0 auto;
        }
        .job-list {
            display: grid;
            gap: 1rem;
        }
        .job-card {
            background-color: var(--card-bg);
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .job-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            border-color: var(--accent);
        }
        .job-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #f8fafc;
            margin: 0 0 0.5rem 0;
        }
        .job-meta {
            display: flex;
            gap: 1rem;
            color: #94a3b8;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        .job-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .apply-btn {
            display: inline-block;
            background-color: var(--accent);
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            transition: background-color 0.2s;
        }
        .apply-btn:hover {
            background-color: var(--accent-hover);
        }
    </style>

    <div class="hero">
        <h2>Find Your Next Great Opportunity</h2>
        <p>Explore the latest jobs from top tech companies and startups around the world.</p>
    </div>

    <h3>Latest Jobs</h3>
    
    <div class="job-list">
        @forelse($jobs ?? [] as $job)
            <div class="job-card">
                <h4 class="job-title">{{ $job['title'] }}</h4>
                <div class="job-meta">
                    <span>🏢 {{ $job['company'] }}</span>
                    <span>📍 {{ $job['location'] }}</span>
                    <span>💼 {{ $job['type'] }}</span>
                </div>
                <a href="#" class="apply-btn">View Details</a>
            </div>
        @empty
            <div class="job-card" style="text-align: center; padding: 3rem;">
                <p style="color: #94a3b8; margin: 0;">No jobs found at the moment. Please check back later!</p>
            </div>
        @endforelse
    </div>
</x-layout>
