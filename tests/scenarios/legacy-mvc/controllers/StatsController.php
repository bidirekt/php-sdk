<?php

class StatsController extends Controller
{
    /**
     * @bidirekt provides GET /stats
     * @bidirekt response 200 StoreStats
     *
     * @bidirekt schema StoreStats
     * @bidirekt field total type=integer
     * @bidirekt field byStatus type=map values=integer description="How many pets have each status"
     * @bidirekt field heaviest type=Pet nullable=true
     *
     * @return void
     */
    public function index()
    {
        $byStatus = array();
        foreach ($this->db->query('SELECT status, COUNT(*) AS total FROM pets GROUP BY status') as $row) {
            $byStatus[$row['status']] = (int) $row['total'];
        }
        $row = $this->db->query('SELECT * FROM pets ORDER BY weight DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);

        $this->json(array(
            'total' => array_sum($byStatus),
            'byStatus' => (object) $byStatus,
            'heaviest' => $row ? Pet::fromRow($row) : null,
        ));
    }
}
