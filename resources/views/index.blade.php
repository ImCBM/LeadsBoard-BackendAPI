<x-layout>
    <style>
        .hero {
            padding: 64px 24px;
            background-color: var(--surface);
            border-radius: 24px; /* shape language: 24px for large hero */
            box-shadow: 0 8px 24px var(--shadow-color);
            margin-top: 48px;
            display: flex;
            flex-direction: column;
            align-items: flex-start; /* Left-align by default */
        }
        .hero h2 {
            font-size: 64px; /* display-lg */
            line-height: 72px;
            letter-spacing: -0.01em;
            margin-bottom: 16px;
            color: var(--on-background);
            max-width: 800px;
        }
        .hero p {
            color: var(--on-surface-variant);
            font-size: 16px; /* body-lg */
            max-width: 600px;
            margin: 0 0 32px 0;
            line-height: 26px;
        }
        .hero .btn {
            background-color: var(--secondary); /* secondary (Coral) for the single most important action */
            color: var(--on-secondary);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 99px; /* pill radius for buttons */
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .hero .btn:hover {
            background-color: var(--secondary-hover);
        }
        .section-header {
            margin-bottom: 32px;
        }
        .section-header h3 {
            font-size: 40px; /* headline-lg */
            margin: 0;
            line-height: 48px;
        }
        .job-list {
            display: grid;
            gap: 24px; /* gutter */
        }
        .job-card {
            background-color: var(--surface); /* Sage/Stone */
            border: 1px solid var(--outline); /* 1px Outline-Variant border */
            border-radius: 12px; /* 12-16px for cards */
            padding: 24px; /* generous internal padding */
            box-shadow: 0 4px 12px var(--shadow-color); /* soft ambient shadow */
            transition: transform 0.2s, box-shadow 0.2s, background-color 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .job-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px var(--shadow-strong);
            background-color: var(--surface-hover);
        }
        .job-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .job-title {
            font-size: 24px; /* headline-md */
            margin: 0;
            color: var(--on-surface);
        }
        .job-meta {
            display: flex;
            gap: 16px;
            color: var(--on-surface-variant);
            font-size: 14px; /* body-md */
        }
        .job-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .apply-btn {
            display: inline-block;
            background-color: var(--primary); /* Primary (Mint) */
            color: var(--on-primary);
            padding: 10px 20px;
            border-radius: 99px; /* Pill radius */
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        .apply-btn:hover {
            background-color: var(--primary-hover);
        }
        @media (max-width: 768px) {
            .hero h2 {
                font-size: 40px;
                line-height: 48px;
            }
            .job-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
        }
    </style>

    <div class="hero">
        <h2>Find Your Next Great Opportunity</h2>
        <p>Explore the latest jobs from top tech companies and startups around the world. Grow your career in an environment that values you.</p>
        <a href="#" class="btn">Post a Job</a>
    </div>

    <div>
        <div class="section-header">
            <h3>Latest Jobs</h3>
        </div>
        
        <div class="job-list">
            @forelse($jobs ?? [] as $job)
                <div class="job-card">
                    <div class="job-details">
                        <h4 class="job-title">{{ $job['title'] }}</h4>
                        <div class="job-meta">
                            <span>🏢 {{ $job['company'] }}</span>
                            <span>📍 {{ $job['location'] }}</span>
                            <span>💼 {{ $job['type'] }}</span>
                        </div>
                    </div>
                    <a href="#" class="apply-btn">View Details</a>
                </div>
            @empty
                <div class="job-card" style="justify-content: center; padding: 48px;">
                    <p style="color: var(--on-surface-variant); margin: 0; text-align: center;">No jobs found at the moment. Please check back later!</p>
                </div>
            @endforelse
        </div>
    </div>
</x-layout>
