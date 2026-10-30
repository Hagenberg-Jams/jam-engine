<?php

namespace HagenbergJams\JamEngine\Helpers;

use HagenbergJams\JamEngine\JamData;

class OpenGraphMeta
{
    protected string $baseUrl;
    protected string $siteName;

    public function __construct()
    {
        $website = config('jam.website', '');
        $this->baseUrl = rtrim(is_string($website) ? $website : '', '/');
        $siteName = config('hyde.name', '');
        $this->siteName = is_string($siteName) ? $siteName : '';
    }

    /**
     * Get Open Graph meta tags for the current page
     *
     * @param array<string, mixed> $context Context variables from Blade template
     * @return array<string, string>
     */
    public function getMetaTags(array $context = []): array
    {
        // Extract context variables with type coercion
        $year = is_int($context['year'] ?? null) ? $context['year'] : null;
        $projectName = is_string($context['projectName'] ?? null) ? $context['projectName'] : null;
        $description = is_string($context['description'] ?? null) ? $context['description'] : null;
        $headerImage = is_string($context['headerImage'] ?? null) ? $context['headerImage'] : null;
        $personName = is_string($context['personName'] ?? null) ? $context['personName'] : null;
        $totalProjects = is_int($context['totalProjects'] ?? null) ? $context['totalProjects'] : 0;
        $years = is_array($context['years'] ?? null) ? $context['years'] : [];
        $persons = $context['persons'] ?? null;
        $jam = is_array($context['jam'] ?? null) ? $context['jam'] : null;
        $projects = is_array($context['projects'] ?? null) ? $context['projects'] : null;

        // Detect page type
        if ($projectName && $year) {
            return $this->getGameMeta($year, $projectName, $description, $headerImage);
        }
        if ($year !== null && $projectName === null) {
            /** @var array<string, mixed>|null $jamTyped */
            $jamTyped = $jam;
            /** @var array<int, array<string, mixed>>|null $projectsTyped */
            $projectsTyped = $projects;
            return $this->getYearMeta($year, $jamTyped, $projectsTyped);
        }
        if ($personName !== null) {
            /** @var array<int> $yearsFiltered */
            $yearsFiltered = array_values(array_filter($years, 'is_int'));
            return $this->getPersonMeta($personName, $totalProjects, $yearsFiltered);
        }
        if ($persons !== null) {
            return $this->getPeopleMeta();
        }

        // Default to homepage if no specific context is detected
        return $this->getHomepageMeta();
    }

    /**
     * @return array<string, string>
     */
    protected function getHomepageMeta(): array
    {
        $homepage = JamData::getHomepage();
        $hero = isset($homepage['hero']) && is_array($homepage['hero']) ? $homepage['hero'] : [];
        $description = isset($hero['description']) && is_string($hero['description']) ? $hero['description'] : $this->siteName;

        // Get first hero image
        $heroImages = isset($hero['images']) && is_array($hero['images']) ? $hero['images'] : [];
        $image = !empty($heroImages) && isset($heroImages[0]) && is_string($heroImages[0]) ? $heroImages[0] : null;
        $imageUrl = $image !== null ? $this->getImageUrl($image) : $this->getFallbackImage();

        return [
            'title' => $this->siteName,
            'description' => $description,
            'image' => $imageUrl,
            'url' => $this->baseUrl,
            'type' => 'website',
            'site_name' => $this->siteName,
        ];
    }

    /**
     * @param array<string, mixed>|null $jam
     * @param array<int, array<string, mixed>>|null $projects
     * @return array<string, string>
     */
    protected function getYearMeta(?int $year = null, ?array $jam = null, ?array $projects = null): array
    {
        if (!$year) {
            return $this->getDefaultMeta();
        }

        if (!$jam) {
            $jam = JamData::getJam($year);
        }
        if (!$projects) {
            $projects = JamData::getProjects($year);
        }

        $title = "{$this->siteName} {$year}";
        $description = $this->label('projects') . " from the {$year} {$this->siteName}";
        if ($jam !== null && isset($jam['topic']) && is_string($jam['topic'])) {
            $description = "{$year} {$this->siteName}: {$jam['topic']}";
        }

        // Find header image: first winner game, or first game
        $image = null;
        foreach ($projects as $entry) {
            if (!is_array($entry) || !isset($entry['winner']) || $entry['winner'] === 'no' || $entry['winner'] === '') {
                continue;
            }
            $headerImage = $entry['headerimage'] ?? '';
            if (is_string($headerImage) && $headerImage !== '') {
                $image = $this->getImageUrl($headerImage, $year);
                break;
            }
        }

        // If no winner image found, use first game's header image
        if (!$image && !empty($projects)) {
            $firstEntry = $projects[0];
            $headerImage = isset($firstEntry['headerimage']) ? $firstEntry['headerimage'] : '';
            if (is_string($headerImage) && $headerImage !== '') {
                $image = $this->getImageUrl($headerImage, $year);
            }
        }

        if (!$image) {
            $image = $this->getFallbackImage();
        }

        return [
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'url' => "{$this->baseUrl}/{$year}",
            'type' => 'website',
            'site_name' => $this->siteName,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getGameMeta(?int $year, ?string $projectName, ?string $description, ?string $headerImage): array
    {
        if (!$year || !$projectName) {
            return $this->getDefaultMeta();
        }

        $title = "{$projectName} - {$this->siteName} {$year}";

        // Truncate description to ~200 characters
        $plainDescription = strip_tags($description ?? '');
        $plainDescription = preg_replace('/\s+/', ' ', $plainDescription) ?? ''; // Normalize whitespace
        $plainDescription = trim($plainDescription);

        $shortDescription = mb_substr($plainDescription, 0, 200);
        if (mb_strlen($plainDescription) > 200) {
            $shortDescription .= '...';
        }

        $image = $headerImage ? $this->getImageUrl($headerImage, $year) : $this->getFallbackImage();

        return [
            'title' => $title,
            'description' => $shortDescription ?: $this->label('meta_verb') . " {$projectName} from the {$year} {$this->siteName}",
            'image' => $image,
            'url' => "{$this->baseUrl}/{$year}/" . \Illuminate\Support\Str::slug($projectName),
            'type' => 'article',
            'site_name' => $this->siteName,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getPeopleMeta(): array
    {
        return [
            'title' => "People - {$this->siteName}",
            'description' => "Meet the participants of the {$this->siteName}",
            'image' => $this->getFallbackImage(),
            'url' => "{$this->baseUrl}/people",
            'type' => 'website',
            'site_name' => $this->siteName,
        ];
    }

    /**
     * @param array<int> $years
     * @return array<string, string>
     */
    protected function getPersonMeta(?string $personName, int $totalProjects, array $years): array
    {
        if (!$personName) {
            return $this->getDefaultMeta();
        }

        $title = "{$personName} - {$this->siteName}";
        $description = "{$personName} has participated in " . count($years) . ' ' . $this->label(count($years) !== 1 ? 'jams' : 'jam') . " with {$totalProjects} " . $this->label($totalProjects !== 1 ? 'projects_lower' : 'project_lower');

        return [
            'title' => $title,
            'description' => $description,
            'image' => $this->getFallbackImage(),
            'url' => "{$this->baseUrl}/person/" . \Illuminate\Support\Str::slug($personName),
            'type' => 'profile',
            'site_name' => $this->siteName,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getDefaultMeta(): array
    {
        $homepage = JamData::getHomepage();
        $hero = isset($homepage['hero']) && is_array($homepage['hero']) ? $homepage['hero'] : [];
        $description = isset($hero['description']) && is_string($hero['description']) ? $hero['description'] : $this->siteName;

        return [
            'title' => $this->siteName,
            'description' => $description,
            'image' => $this->getFallbackImage(),
            'url' => $this->baseUrl,
            'type' => 'website',
            'site_name' => $this->siteName,
        ];
    }

    protected function label(string $key): string
    {
        $label = config("jam.labels.{$key}", '');

        return is_string($label) ? $label : '';
    }

    protected function getImageUrl(string $imagePath, ?int $year = null): string
    {
        // Remove leading slash if present
        $imagePath = ltrim($imagePath, '/');

        // If image path doesn't start with /media/, add it
        if (!str_starts_with($imagePath, 'media/')) {
            if ($year) {
                $imagePath = "media/{$year}/{$imagePath}";
            } else {
                $imagePath = "media/{$imagePath}";
            }
        }

        // Ensure it starts with /
        if (!str_starts_with($imagePath, '/')) {
            $imagePath = '/' . $imagePath;
        }

        return $this->baseUrl . $imagePath;
    }

    protected function getFallbackImage(): string
    {
        $homepage = JamData::getHomepage();
        $hero = isset($homepage['hero']) && is_array($homepage['hero']) ? $homepage['hero'] : [];
        $heroImages = isset($hero['images']) && is_array($hero['images']) ? $hero['images'] : [];

        if (!empty($heroImages) && isset($heroImages[0]) && is_string($heroImages[0]) && $heroImages[0] !== '') {
            return $this->getImageUrl($heroImages[0]);
        }

        return $this->baseUrl;
    }

}
