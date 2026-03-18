<div class="dashboard-card nutrition-card">
  <div class="dashboard-card-header">
    <span>Nutrition & Analytics</span>
  </div>
  <div class="dashboard-card-body p-2">
    <!-- Simple Tabs -->
    <ul class="nav nav-tabs" id="nutritionTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="nutrition-tab" data-bs-toggle="tab" data-bs-target="#nutrition" type="button" role="tab">
          Nutrition
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab">
          Analytics
        </button>
      </li>
    </ul>
    
    <!-- Tab Content -->
    <div class="tab-content mt-3">
      <!-- Nutrition Tab -->
      <div class="tab-pane fade show active" id="nutrition" role="tabpanel">
        <?php if ($nutritionStats['totalEntries'] > 0): ?>
          <!-- Nutrition Overview -->
        <div class="row text-center mb-4">
        <div class="col-4">
            <div class="p-2 rounded shadow-sm border">
            <h3 class="mb-0"><?php echo $nutritionStats['healthScore']; ?></h3>
            <small class="text-muted">Health Score</small>
            </div>
        </div>
        <div class="col-4">
            <div class="p-2 rounded shadow-sm border">
            <h3 class="mb-0"><?php echo $nutritionStats['totalEntries']; ?></h3>
            <small class="text-muted">Items</small>
            </div>
        </div>
        <div class="col-4">
            <div class="p-2 rounded shadow-sm border">
            <h3 class="mb-0"><?php echo $nutritionStats['healthyDays']; ?></h3>
            <small class="text-muted">Healthy Days</small>
            </div>
        </div>
        </div>

        <!-- Recent Items Table -->
        <h6 class="fw-bold mb-2">Recent Items</h6>
        <div class="table-responsive">
        <table class="table table-hover table-sm">
            <thead>
            <tr>
                <th>Image</th>
                <th>Date</th>
                <th>Score</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($recentNutritionEntries)): ?>
                <tr>
                <td colspan="4" class="text-center">No entries found</td>
                </tr>
            <?php else: ?>
                <?php foreach ($recentNutritionEntries as $entry): 
                $scoreInfo = formatProductScore($entry['ProductScore']);
                $scoreLabel = $scoreInfo[0];
                $scoreClass = $scoreInfo[1];
                ?>
                <tr>
                    <td style="width: 50px;">
                    <?php if (!empty($entry['ProductImage'])): ?>
                        <img src="<?php echo htmlspecialchars($entry['ProductImage']); ?>" alt="Food" class="img-fluid rounded" style="width: 40px; height: 40px; object-fit: cover;">
                    <?php else: ?>
                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fas fa-utensils text-secondary"></i>
                        </div>
                    <?php endif; ?>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($entry['DetectedAt'])); ?></td>
                    <td><?php echo htmlspecialchars($entry['ProductScore']); ?></td>
                    <td>
                    <span class="badge bg-<?php echo $scoreClass; ?>"><?php echo $scoreLabel; ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php else: ?>
        <!-- No data state -->
        <div class="text-center py-4">
        <div class="mb-3">
            <i class="fas fa-utensils fa-3x text-muted"></i>
        </div>
        <h5>No nutrition data yet</h5>
        <p class="text-muted">Track your first food item to see analytics</p>
        <a href="ProductDetection.php" class="btn btn-primary">Scan Food Item</a>
        </div>
        <?php endif; ?>
      </div>
      
      <!-- Analytics Tab -->
      <div class="tab-pane fade" id="analytics" role="tabpanel">
        <div class="row mt-4">
          <div class="col-12">
            <h6 class="fw-bold mb-2">Nutrition Distribution</h6>
            <div style="height: 250px;">
              <canvas id="healthDistributionChart"></canvas>
            </div>
          </div>
        </div>
        <?php if ($nutritionStats['totalEntries'] > 0): ?>
            <!-- Simple Analytics Overview -->
            <div class="row text-center mb-4">
            <div class="col-6">
                <div class="py-3">
                <div class="d-inline-block position-relative">
                    <svg width="80" height="80">
                    <circle cx="40" cy="40" r="35" fill="none" stroke="#e9ecef" stroke-width="8" />
                    <circle 
                        cx="40" 
                        cy="40" 
                        r="35" 
                        fill="none" 
                        stroke="#28a745" 
                        stroke-width="8" 
                        stroke-dasharray="220" 
                        stroke-dashoffset="<?php echo 220 * (1 - $nutritionStats['healthScore']/100); ?>" 
                        transform="rotate(-90 40 40)" 
                    />
                    </svg>
                    <div class="position-absolute top-50 start-50 translate-middle">
                    <h5 class="mb-0"><?php echo $nutritionStats['healthScore']; ?>%</h5>
                    </div>
                </div>
                <div class="mt-2">Healthy Choices</div>
                </div>
            </div>
            <div class="col-6">
                <div class="py-3">
                <div class="d-inline-block position-relative">
                    <svg width="80" height="80">
                    <circle cx="40" cy="40" r="35" fill="none" stroke="#e9ecef" stroke-width="8" />
                    <circle 
                        cx="40" 
                        cy="40" 
                        r="35" 
                        fill="none" 
                        stroke="#17a2b8" 
                        stroke-width="8" 
                        stroke-dasharray="220" 
                        stroke-dashoffset="<?php echo 220 * (1 - ($nutritionStats['healthyDays'] / max(1, $nutritionStats['totalEntries']))); ?>" 
                        transform="rotate(-90 40 40)" 
                    />
                    </svg>
                    <div class="position-absolute top-50 start-50 translate-middle">
                    <h5 class="mb-0"><?php echo $nutritionStats['healthyDays']; ?></h5>
                    </div>
                </div>
                <div class="mt-2">Healthy Days</div>
                </div>
            </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-2 mb-3">
            <div class="col-6">
                <div class="border rounded p-3 text-center">
                <div class="small text-muted">Total Items</div>
                <div class="fs-5 fw-bold"><?php echo $nutritionStats['totalEntries']; ?></div>
                </div>
            </div>
            <div class="col-6">
                <div class="border rounded p-3 text-center">
                <div class="small text-muted">Health %</div>
                <div class="fs-5 fw-bold"><?php echo $nutritionStats['healthScore']; ?>%</div>
                </div>
            </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <h6 class="fw-bold mb-2">Nutrition Distribution</h6>
                    <div style="height: 250px;">
                        <canvas id="healthDistributionChart"></canvas>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- No data state -->
            <div class="text-center py-4">
            <div class="mb-3">
                <i class="fas fa-chart-pie fa-3x text-muted"></i>
            </div>
            <h5>No analytics available</h5>
            <p class="text-muted">Track food items to see your nutrition analytics</p>
            </div>
            <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Debug Data Elements -->
<div class="debug-data" style="display:none;">
    <div id="nutritionEntriesData"></div>
    <div id="nutritionStatsData"></div>
</div>