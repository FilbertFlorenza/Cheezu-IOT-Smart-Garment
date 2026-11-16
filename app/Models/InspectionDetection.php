<?php

namespace App\Models;

use Database\Factories\InspectionDetectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionDetection extends Model
{
    /** @use HasFactory<InspectionDetectionFactory> */
    use HasFactory;

    protected $fillable = [
        'inspection_id',
        'detection_index',
        'defect_class',
        'confidence',
        'bbox_x',
        'bbox_y',
        'bbox_width',
        'bbox_height',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'detection_index' => 'integer',
            'confidence' => 'float',
            'bbox_x' => 'integer',
            'bbox_y' => 'integer',
            'bbox_width' => 'integer',
            'bbox_height' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    /**
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    public function boundingBox(): ?array
    {
        if ($this->bbox_width === null || $this->bbox_height === null) {
            return null;
        }

        return [
            'x' => $this->bbox_x ?? 0,
            'y' => $this->bbox_y ?? 0,
            'width' => $this->bbox_width,
            'height' => $this->bbox_height,
        ];
    }
}
